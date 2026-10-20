<?php

namespace App\Services;

use App\Models\MBarang;
use App\Models\MCustomer;
use App\Models\SalesOrder;
use App\Support\SalesAmounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function save(array $data, ?SalesOrder $order = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $order) {
            if ($order) {
                $order = SalesOrder::lockForUpdate()->findOrFail($order->getKey());
                if ($order->invoices()->exists()) {
                    throw ValidationException::withMessages(['document' => 'SO yang sudah memiliki invoice tidak dapat diubah.']);
                }
            }
            if (! MCustomer::lockForUpdate()->find($data['id_customer'])) {
                throw ValidationException::withMessages(['id_customer' => 'Customer sudah tidak tersedia.']);
            }
            $totals = SalesAmounts::calculate($data['items']);
            $barangs = MBarang::whereIn('id_barang', array_column($data['items'], 'id_barang'))
                ->orderBy('id_barang')->lockForUpdate()->get()->keyBy('id_barang');
            $order ??= new SalesOrder;
            $order->fill([
                'id_customer' => $data['id_customer'], 'tanggal' => $data['tanggal'],
                'total_harga' => $totals['total_harga'],
            ])->save();
            $order->details()->delete();
            foreach ($totals['items'] as $index => $item) {
                $barang = $barangs->get($item['id_barang']);
                if (! $barang) {
                    throw ValidationException::withMessages(["items.$index.id_barang" => 'Barang sudah tidak tersedia.']);
                }
                $order->details()->create([
                    'id_barang' => $barang->getKey(), 'sku' => $barang->sku, 'nama_barang' => $barang->nama_barang,
                    'kuantitas' => $item['kuantitas'], 'harga_jual' => $item['harga_jual'],
                    'diskon' => $item['diskon'], 'total_harga' => $item['total_harga'],
                ]);
            }

            return $order;
        });
    }

    // Called inside the invoice transaction after locking the SO header.
    public function assertInvoiceable(SalesOrder $order): void
    {
        $details = $order->details()->lockForUpdate()->get();
        if (! $order->customer()->exists() || $details->isEmpty()) {
            throw ValidationException::withMessages(['id_sales_order' => 'SO harus memiliki customer valid dan minimal satu detail barang.']);
        }
        try {
            $totals = SalesAmounts::calculate($details->toArray());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['id_sales_order' => 'Detail SO tidak valid. Periksa barang, kuantitas, harga, dan diskon sebelum membuat invoice.']);
        }
        if ($totals['total_harga'] !== $order->total_harga) {
            throw ValidationException::withMessages(['id_sales_order' => 'Total SO tidak sesuai rincian barang. Perbaiki SO sebelum membuat invoice.']);
        }
        foreach ($details as $index => $detail) {
            if ($totals['items'][$index]['total_harga'] !== $detail->total_harga) {
                throw ValidationException::withMessages(['id_sales_order' => 'Total baris SO tidak konsisten. Perbaiki SO sebelum membuat invoice.']);
            }
        }
    }
}
