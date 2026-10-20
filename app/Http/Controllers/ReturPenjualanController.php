<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturPenjualanRequest;
use App\Models\InvoicePenjualan;
use App\Models\ReturPenjualan;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturPenjualanController extends Controller
{
    public function index()
    {
        $records = ReturPenjualan::with('invoicePenjualan')->latest()->get();

        return view('retur-penjualan.index', compact('records'));
    }

    public function create(Request $request)
    {
        $invoices = InvoicePenjualan::with('salesOrder.customer')->latest()->get();
        $selectedInvoice = $request->query('invoice');
        $documentNumber = app(DocumentNumberService::class)->preview(ReturPenjualan::class);

        return view('retur-penjualan.create', compact('invoices', 'selectedInvoice', 'documentNumber'));
    }

    public function store(ReturPenjualanRequest $request)
    {
        $data = $request->validated();
        $document = DB::transaction(function () use ($data) {
            $invoice = InvoicePenjualan::lockForUpdate()->findOrFail($data['id_invoice_penjualan']);
            if ($data['tanggal_retur'] < $invoice->tanggal_invoice) {
                throw ValidationException::withMessages(['tanggal_retur' => 'Tanggal retur tidak boleh sebelum tanggal invoice.']);
            }

            return ReturPenjualan::create($data);
        });

        return redirect()->route('retur-penjualan.show', $document->getKey())
            ->with('success', 'Retur penjualan berhasil ditambahkan.');
    }

    public function show(ReturPenjualan $retur_penjualan)
    {
        $document = $retur_penjualan->load('invoicePenjualan.salesOrder.customer');

        return view('retur-penjualan.show', compact('document'));
    }
}
