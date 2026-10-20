<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\DeletesPurchasingDocuments;
use App\Models\MBarang;
use App\Models\PermintaanPembelian;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\PurchasingStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PermintaanPembelianController extends Controller
{
    use DeletesPurchasingDocuments;

    public function index()
    {
        $records = PermintaanPembelian::with(['pemohon', 'penyetuju'])->latest()->get();

        return view('permintaan-pembelian.index', compact('records'));
    }

    public function create()
    {
        $documentNumber = app(DocumentNumberService::class)->preview(PermintaanPembelian::class);
        $approvers = $this->approvers();

        $barangs = MBarang::orderBy('nama_barang')->get();

        return view('permintaan-pembelian.create', compact('documentNumber', 'approvers', 'barangs'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $document = DB::transaction(function () use ($data) {
            $this->checkUsersAndApproval($data);
            $document = PermintaanPembelian::create(collect($data)->except('items')->all());
            $this->saveDetails($document, $data['items']);

            return $document;
        });

        return redirect()->route('permintaan-pembelian.show', $document->getKey())
            ->with('success', 'Permintaan pembelian berhasil ditambahkan.');
    }

    public function show(PermintaanPembelian $permintaan_pembelian)
    {
        $document = $permintaan_pembelian->load(['pemohon', 'penyetuju', 'details']);

        return view('permintaan-pembelian.show', compact('document'));
    }

    public function edit(PermintaanPembelian $permintaan_pembelian)
    {
        if ($permintaan_pembelian->purchaseOrders()->exists()) {
            return redirect()->route('permintaan-pembelian.show', $permintaan_pembelian->getKey())
                ->withErrors(['document' => 'Permintaan yang sudah digunakan pada Purchase Order tidak dapat diubah.']);
        }
        $document = $permintaan_pembelian->load(['pemohon', 'details']);
        $approvers = $this->approvers();

        $barangs = MBarang::orderBy('nama_barang')->get();

        return view('permintaan-pembelian.edit', compact('document', 'approvers', 'barangs'));
    }

    public function update(Request $request, PermintaanPembelian $permintaan_pembelian)
    {
        DB::transaction(function () use ($request, $permintaan_pembelian) {
            $locked = PermintaanPembelian::lockForUpdate()->findOrFail($permintaan_pembelian->getKey());
            if ($locked->purchaseOrders()->exists()) {
                throw ValidationException::withMessages(['document' => 'Permintaan yang sudah digunakan pada Purchase Order tidak dapat diubah.']);
            }
            $data = $this->validated($request, $locked);
            if (in_array($locked->status, ['Disetujui', 'Ditolak'], true)) {
                Gate::authorize('approve-purchase-request', (int) $locked->id_penyetuju);
            }
            $this->checkUsersAndApproval($data);
            $locked->update(collect($data)->except('items')->all());
            if (isset($data['items'])) {
                $this->saveDetails($locked, $data['items']);
            }
        });

        return redirect()->route('permintaan-pembelian.show', $permintaan_pembelian->getKey())
            ->with('success', 'Permintaan pembelian berhasil diperbarui.');
    }

    public function destroy(PermintaanPembelian $permintaan_pembelian)
    {
        return $this->deleteDocument($permintaan_pembelian, 'permintaan-pembelian.index', 'Permintaan pembelian');
    }

    private function validated(Request $request, ?PermintaanPembelian $document = null): array
    {
        $requesterId = $document?->id_pemohon ?? $request->user()->getKey();
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_diperlukan' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal'],
            'id_pemohon' => ['sometimes', 'required', 'integer', Rule::in([$requesterId]), 'exists:users,id'],
            'id_penyetuju' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', PurchasingStatuses::APPROVER_ROLES)],
            'status' => ['required', Rule::in(PurchasingStatuses::REQUEST)],
            'items' => [$document && ! $document->details()->exists() ? 'sometimes' : 'required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => ['required', 'integer', 'exists:m_barang,id_barang'],
            'items.*.kuantitas' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.tanggal_dibutuhkan' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal'],
            'items.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['id_pemohon'] = $requesterId;

        return $data;
    }

    private function saveDetails(PermintaanPembelian $document, array $items): void
    {
        $barangs = MBarang::whereIn('id_barang', array_column($items, 'id_barang'))->orderBy('id_barang')->lockForUpdate()->get()->keyBy('id_barang');
        $document->details()->delete();
        foreach ($items as $index => $item) {
            $barang = $barangs->get($item['id_barang']);
            if (! $barang) {
                throw ValidationException::withMessages(["items.$index.id_barang" => 'Barang sudah tidak tersedia.']);
            }
            $document->details()->create([
                'id_barang' => $barang->getKey(), 'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang,
                'kuantitas' => $item['kuantitas'], 'tanggal_dibutuhkan' => $item['tanggal_dibutuhkan'], 'keterangan' => $item['keterangan'] ?? null,
            ]);
        }
    }

    private function approvers()
    {
        return User::whereIn('role', PurchasingStatuses::APPROVER_ROLES)->orderBy('name')->get(['id', 'name']);
    }

    private function checkUsersAndApproval(array $data): void
    {
        $users = User::whereIn('id', [$data['id_pemohon'], $data['id_penyetuju']])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if (! $users->has($data['id_pemohon'])) {
            throw ValidationException::withMessages(['id_pemohon' => 'Pemohon harus merujuk pengguna yang masih tersedia.']);
        }
        $approver = $users->get($data['id_penyetuju']);
        if (! $approver || ! in_array($approver->role, PurchasingStatuses::APPROVER_ROLES, true)) {
            throw ValidationException::withMessages(['id_penyetuju' => 'Penyetuju harus pengguna dengan role Admin atau Purchasing.']);
        }
        if (in_array($data['status'], ['Disetujui', 'Ditolak'], true)) {
            Gate::authorize('approve-purchase-request', (int) $data['id_penyetuju']);
        }
    }
}
