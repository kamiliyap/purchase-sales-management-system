<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesOrderRequest;
use App\Models\MBarang;
use App\Models\MCustomer;
use App\Models\SalesOrder;
use App\Services\DocumentNumberService;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function index()
    {
        $records = SalesOrder::with('customer')->withExists('invoices')->latest()->get();

        return view('sales-order.index', compact('records'));
    }

    public function create()
    {
        return view('sales-order.create', $this->choices());
    }

    public function store(SalesOrderRequest $request, SalesOrderService $service)
    {
        $document = $service->save($request->validated());

        return redirect()->route('sales-order.show', $document->getKey())
            ->with('success', 'Sales Order dan seluruh item berhasil disimpan.');
    }

    public function show(SalesOrder $sales_order)
    {
        $document = $sales_order->load(['customer', 'details', 'invoices']);

        return view('sales-order.show', compact('document'));
    }

    public function edit(SalesOrder $sales_order)
    {
        if ($sales_order->invoices()->exists()) {
            return redirect()->route('sales-order.show', $sales_order->getKey())
                ->withErrors(['document' => 'SO yang sudah memiliki invoice tidak dapat diubah.']);
        }
        $document = $sales_order->load('details');

        return view('sales-order.edit', array_merge($this->choices(), compact('document')));
    }

    public function update(SalesOrderRequest $request, SalesOrder $sales_order, SalesOrderService $service)
    {
        $document = $service->save($request->validated(), $sales_order);

        return redirect()->route('sales-order.show', $document->getKey())
            ->with('success', 'Sales Order berhasil diperbarui.');
    }

    public function destroy(SalesOrder $sales_order)
    {
        DB::transaction(function () use ($sales_order) {
            $locked = SalesOrder::lockForUpdate()->findOrFail($sales_order->getKey());
            if ($locked->invoices()->exists()) {
                throw ValidationException::withMessages(['document' => 'SO yang sudah memiliki invoice tidak dapat dihapus.']);
            }
            $locked->delete();
        });

        return redirect()->route('sales-order.index')->with('success', 'Sales Order berhasil dihapus.');
    }

    private function choices(): array
    {
        return [
            'documentNumber' => app(DocumentNumberService::class)->preview(SalesOrder::class),
            'customers' => MCustomer::orderBy('nama_customer')->get(),
            'barangs' => MBarang::orderBy('nama_barang')->get(),
        ];
    }
}
