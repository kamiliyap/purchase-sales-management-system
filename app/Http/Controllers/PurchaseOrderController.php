<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DeletesPurchasingDocuments;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Services\DocumentNumberService;
use App\Support\PurchasingAmounts;
use App\Support\PurchasingStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    use DeletesPurchasingDocuments;

    public function index()
    {
        $records = PurchaseOrder::with(['supplier', 'permintaanPembelian'])->withExists('invoices')->latest()->get();

        return view('purchase-order.index', compact('records'));
    }

    public function create(Request $request)
    {
        $request->validate(['id_permintaan' => ['sometimes', 'integer']]);
        $selectedRequest = $request->filled('id_permintaan') ? PermintaanPembelian::with('details.barang')->where('status', 'Disetujui')->findOrFail($request->query('id_permintaan')) : null;
        $requestItems = $selectedRequest?->details->map(fn ($line) => ['id_barang' => $line->id_barang, 'kuantitas' => $line->kuantitas, 'harga_unit' => $line->barang?->harga_beli ?? '', 'diskon' => 0])->all();

        return view('purchase-order.create', array_merge($this->choices(), compact('selectedRequest', 'requestItems')));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $document = DB::transaction(fn () => $this->saveOrder($data));

        return redirect()->route('purchase-order.show', $document->getKey())
            ->with('success', 'Purchase Order dan seluruh item berhasil disimpan.');
    }

    public function show(PurchaseOrder $purchase_order)
    {
        $document = $purchase_order->load(['supplier', 'permintaanPembelian', 'details', 'invoices']);

        return view('purchase-order.show', compact('document'));
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->invoices()->exists() || $purchase_order->penerimaanBarang()->exists()) {
            return redirect()->route('purchase-order.show', $purchase_order->getKey())
                ->withErrors(['document' => 'PO yang sudah memiliki penerimaan atau invoice tidak dapat diubah.']);
        }
        $document = $purchase_order->load('details');

        return view('purchase-order.edit', array_merge($this->choices(), compact('document')));
    }

    public function update(Request $request, PurchaseOrder $purchase_order)
    {
        $data = $this->validated($request);
        $document = DB::transaction(function () use ($data, $purchase_order) {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($purchase_order->getKey());
            if ($locked->invoices()->exists() || $locked->penerimaanBarang()->exists()) {
                throw ValidationException::withMessages(['document' => 'PO yang sudah memiliki penerimaan atau invoice tidak dapat diubah.']);
            }

            return $this->saveOrder($data, $locked);
        });

        return redirect()->route('purchase-order.show', $document->getKey())
            ->with('success', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        return $this->deleteDocument($purchase_order, 'purchase-order.index', 'Purchase Order');
    }

    private function choices(): array
    {
        return [
            'documentNumber' => app(DocumentNumberService::class)->preview(PurchaseOrder::class),
            'requests' => PermintaanPembelian::where('status', 'Disetujui')->latest()->get(),
            'suppliers' => MSupplier::orderBy('nama_supplier')->get(),
            'barangs' => MBarang::orderBy('nama_barang')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $money = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];

        return $request->validate([
            'status' => ['prohibited'],
            'id_permintaan' => ['required', 'integer', 'exists:permintaan_pembelian,id_permintaan'],
            'id_supplier' => ['required', 'integer', 'exists:m_supplier,id_supplier'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_dibutuhkan' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal'],
            'ppn_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => ['required', 'integer', 'exists:m_barang,id_barang'],
            'items.*.kuantitas' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.harga_unit' => $money,
            'items.*.diskon' => $money,
        ]);
    }

    private function saveOrder(array $data, ?PurchaseOrder $order = null): PurchaseOrder
    {
        $purchaseRequest = PermintaanPembelian::lockForUpdate()->find($data['id_permintaan']);
        if (! $purchaseRequest || $purchaseRequest->status !== 'Disetujui') {
            throw ValidationException::withMessages(['id_permintaan' => 'Purchase Order hanya dapat dibuat dari permintaan yang sudah disetujui.']);
        }
        if (! $purchaseRequest->pemohon()->exists() || ! $purchaseRequest->penyetuju()->whereIn('role', PurchasingStatuses::APPROVER_ROLES)->exists()) {
            throw ValidationException::withMessages(['id_permintaan' => 'Identitas pemohon atau penyetuju permintaan tidak valid. Tinjau permintaan terlebih dahulu.']);
        }
        if ($data['tanggal'] < $purchaseRequest->tanggal) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal PO tidak boleh sebelum tanggal permintaan.']);
        }
        MSupplier::lockForUpdate()->findOrFail($data['id_supplier']);
        $items = $data['items'];
        $totals = PurchasingAmounts::calculate($items, (string) $data['ppn_rate']);
        $barangs = MBarang::whereIn('id_barang', array_column($items, 'id_barang'))
            ->orderBy('id_barang')->lockForUpdate()->get()->keyBy('id_barang');
        $header = array_intersect_key($data, array_flip([
            'id_permintaan', 'id_supplier', 'tanggal', 'tanggal_dibutuhkan',
        ]));
        $header += array_intersect_key($totals, array_flip(['jumlah', 'ppn', 'total_harga']));
        $order ??= new PurchaseOrder;
        $order->fill($header)->save();
        $order->details()->delete();
        foreach ($totals['items'] as $index => $item) {
            $barang = $barangs->get($item['id_barang']);
            if ($barang === null) {
                throw ValidationException::withMessages(["items.$index.id_barang" => 'Barang sudah tidak tersedia.']);
            }
            $order->details()->create([
                'id_barang' => $barang->id_barang, 'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang,
                'kuantitas' => $item['kuantitas'], 'harga_unit' => $item['harga_unit'],
                'diskon' => $item['diskon'], 'total_harga' => $item['total_harga'],
            ]);
        }

        return $order;
    }
}
