<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturPembelianRequest;
use App\Http\Requests\UpdateReturPembelianStatusRequest;
use App\Models\PenerimaanBarang;
use App\Models\ReturPembelian;
use App\Services\DocumentNumberService;
use App\Services\ReturPembelianService;
use Illuminate\Http\Request;

class ReturPembelianController extends Controller
{
    public function index()
    {
        $records = ReturPembelian::with(['penerimaanBarang.purchaseOrder.supplier', 'invoicePembelian'])->latest()->get();

        return view('retur-pembelian.index', compact('records'));
    }

    public function create(Request $request)
    {
        $request->validate(['penerimaan' => ['sometimes', 'integer']]);
        $receipts = PenerimaanBarang::with('purchaseOrder.supplier')->whereHas('details', fn ($q) => $q->where('kondisi', 'Rusak'))->latest()->get();
        $receipt = $request->filled('penerimaan') ? PenerimaanBarang::with(['purchaseOrder.supplier', 'purchaseOrder.details', 'details.detailRetur.returPembelian', 'details.returAsal'])->findOrFail($request->query('penerimaan')) : null;
        $documentNumber = app(DocumentNumberService::class)->preview(ReturPembelian::class);

        return view('retur-pembelian.create', compact('receipts', 'receipt', 'documentNumber'));
    }

    public function store(StoreReturPembelianRequest $request, ReturPembelianService $service)
    {
        $document = $service->create($request->validated(), $request->user()->id);

        return redirect()->route('retur-pembelian.show', $document)->with('success', 'Retur diajukan dan kuantitas Rusak direservasi.');
    }

    public function update(UpdateReturPembelianStatusRequest $request, ReturPembelian $retur_pembelian, ReturPembelianService $service)
    {
        $document = $service->transition($retur_pembelian, $request->validated(), $request->user()->id);

        return redirect()->route('retur-pembelian.show', $document)->with('success', 'Status retur diperbarui. Selesai berarti barang telah dikirim ke supplier.');
    }

    public function show(ReturPembelian $retur_pembelian)
    {
        $document = $retur_pembelian->load(['penerimaanBarang.purchaseOrder.supplier', 'invoicePembelian.purchaseOrder.supplier', 'details.detailPenerimaan.returAsal.returPembelian', 'details.pengganti.penerimaanBarang', 'pembuat', 'penyetuju', 'pengirim']);

        return view('retur-pembelian.show', compact('document'));
    }
}
