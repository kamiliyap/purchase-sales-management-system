<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePenerimaanBarangRequest;
use App\Http\Requests\UpdatePenerimaanBarangRequest;
use App\Models\PenerimaanBarang;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Services\DocumentNumberService;
use App\Services\PenerimaanBarangService;
use Illuminate\Http\Request;

class PenerimaanBarangController extends Controller
{
    public function index()
    {
        $records = PenerimaanBarang::with(['purchaseOrder', 'penerima'])->latest()->get();

        return view('penerimaan-barang.index', compact('records'));
    }

    public function create(Request $request)
    {
        $request->validate(['purchase_order' => ['sometimes', 'nullable', 'integer'], 'retur' => ['sometimes', 'nullable', 'integer']]);
        $document = new PenerimaanBarang;
        $replacementReturn = $request->filled('retur') ? ReturPembelian::with(['penerimaanBarang', 'details.pengganti', 'details.detailPenerimaan'])->where('status', 'Selesai')->whereNotNull('id_penerimaan')->findOrFail($request->query('retur')) : null;
        $order = PurchaseOrder::with('details')->find($replacementReturn?->penerimaanBarang->id_purchase_order ?? $request->query('purchase_order'));

        return $this->form($document, $order, $replacementReturn);
    }

    public function store(StorePenerimaanBarangRequest $request, PenerimaanBarangService $service)
    {
        $document = $service->save($request->validated(), $request->user()->id);

        return redirect()->route('penerimaan-barang.show', $document)->with('success', 'Penerimaan barang berhasil disimpan.');
    }

    public function show(PenerimaanBarang $penerimaan_barang)
    {
        $document = $penerimaan_barang->load(['purchaseOrder.details', 'details.barang', 'details.returAsal.returPembelian', 'returPembelian', 'penerima']);

        return view('penerimaan-barang.show', compact('document'));
    }

    public function edit(PenerimaanBarang $penerimaan_barang)
    {
        if ($penerimaan_barang->returPembelian()->exists() || $penerimaan_barang->details()->whereNotNull('id_detail_retur_asal')->exists()) {
            return redirect()->route('penerimaan-barang.show', $penerimaan_barang)->withErrors(['document' => 'Penerimaan dengan jejak retur/pengganti tidak dapat diubah.']);
        }

        return $this->form($penerimaan_barang->load('details'), $penerimaan_barang->purchaseOrder->load('details'));
    }

    public function update(UpdatePenerimaanBarangRequest $request, PenerimaanBarang $penerimaan_barang, PenerimaanBarangService $service)
    {
        $document = $service->save($request->validated(), $request->user()->id, $penerimaan_barang);

        return redirect()->route('penerimaan-barang.show', $document)->with('success', 'Penerimaan barang diperbarui.');
    }

    public function destroy(PenerimaanBarang $penerimaan_barang, PenerimaanBarangService $service)
    {
        $service->delete($penerimaan_barang);

        return redirect()->route('penerimaan-barang.index')->with('success', 'Penerimaan barang dihapus.');
    }

    private function form(PenerimaanBarang $document, ?PurchaseOrder $order, ?ReturPembelian $replacementReturn = null)
    {
        $orders = PurchaseOrder::latest()->get();
        $documentNumber = $document->no_penerimaan ?? app(DocumentNumberService::class)->preview(PenerimaanBarang::class);

        $returnChoices = $order ? ReturPembelian::whereHas('penerimaanBarang', fn ($q) => $q->where('id_purchase_order', $order->getKey()))->where('status', 'Selesai')->get() : collect();
        $replacementRows = $replacementReturn?->details->map(fn ($line) => [
            'id_barang' => $line->detailPenerimaan->id_barang, 'kuantitas' => $line->kuantitas - $line->pengganti->sum('kuantitas'),
            'id_detail_retur_asal' => $line->getKey(), 'kondisi' => 'Baik',
        ])->filter(fn ($line) => $line['kuantitas'] > 0)->values()->all();

        return view('penerimaan-barang.form', compact('document', 'order', 'orders', 'documentNumber', 'replacementReturn', 'returnChoices', 'replacementRows'));
    }
}
