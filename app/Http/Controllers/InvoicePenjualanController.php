<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoicePenjualanRequest;
use App\Models\InvoicePenjualan;
use App\Models\SalesOrder;
use App\Services\DocumentNumberService;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePenjualanController extends Controller
{
    public function index()
    {
        $records = InvoicePenjualan::with('salesOrder')->latest()->get();

        return view('invoice-penjualan.index', compact('records'));
    }

    public function create(Request $request)
    {
        $orders = SalesOrder::with('customer')->doesntHave('invoices')->has('details')->latest()->get();
        $selectedOrder = $request->query('sales_order');
        $documentNumber = app(DocumentNumberService::class)->preview(InvoicePenjualan::class);

        return view('invoice-penjualan.create', compact('orders', 'selectedOrder', 'documentNumber'));
    }

    public function store(InvoicePenjualanRequest $request)
    {
        $data = $request->validated();
        $document = DB::transaction(function () use ($data) {
            $order = SalesOrder::lockForUpdate()->findOrFail($data['id_sales_order']);
            if ($order->invoices()->exists()) {
                throw ValidationException::withMessages(['id_sales_order' => 'SO ini sudah memiliki invoice penjualan. Satu SO hanya boleh memiliki satu full invoice.']);
            }
            if ($data['tanggal_invoice'] < $order->tanggal) {
                throw ValidationException::withMessages(['tanggal_invoice' => 'Tanggal invoice tidak boleh sebelum tanggal SO.']);
            }

            app(SalesOrderService::class)->assertInvoiceable($order);
            $data['total_invoice'] = $order->total_harga;

            return InvoicePenjualan::create($data);
        });

        return redirect()->route('invoice-penjualan.show', $document->getKey())
            ->with('success', 'Invoice penjualan berhasil ditambahkan.');
    }

    public function show(InvoicePenjualan $invoice_penjualan)
    {
        $document = $invoice_penjualan->load(['salesOrder.customer', 'returPenjualan']);

        return view('invoice-penjualan.show', compact('document'));
    }
}
