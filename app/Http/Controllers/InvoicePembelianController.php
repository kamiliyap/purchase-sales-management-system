<?php

namespace App\Http\Controllers;

use App\Models\InvoicePembelian;
use App\Support\PurchasingStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoicePembelianController extends Controller
{
    public function index()
    {
        $records = InvoicePembelian::with(['purchaseOrder.penerimaanBarang'])->latest()->get();

        return view('invoice-pembelian.index', compact('records'));
    }

    public function create()
    {
        return redirect()->route('invoice-pembelian.index')->with('success', 'Invoice dibuat otomatis setelah seluruh barang PO diterima dan diverifikasi Baik.');
    }

    public function store()
    {
        abort(403, 'Pembuatan invoice manual dinonaktifkan. Invoice dibuat otomatis dari penerimaan lengkap berkondisi Baik.');
    }

    public function update(Request $request, InvoicePembelian $invoice_pembelian)
    {
        $data = $request->validate(['status' => ['required', Rule::in(PurchasingStatuses::INVOICE)]]);
        DB::transaction(function () use ($data, $invoice_pembelian) {
            $invoice = InvoicePembelian::lockForUpdate()->findOrFail($invoice_pembelian->getKey());
            $invoice->update(['status' => $data['status']]);
        });

        return redirect()->route('invoice-pembelian.show', $invoice_pembelian)->with('success', 'Status invoice diperbarui.');
    }

    public function show(InvoicePembelian $invoice_pembelian)
    {
        $document = $invoice_pembelian->load(['purchaseOrder.supplier', 'purchaseOrder.penerimaanBarang', 'returPembelian']);

        return view('invoice-pembelian.show', compact('document'));
    }
}
