<?php

namespace App\Services;

use App\Models\DetailPenerimaanBarang;
use App\Models\InvoicePembelian;
use App\Models\PurchaseOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AutomaticPurchaseInvoiceService
{
    public function createIfComplete(int $orderId): ?InvoicePembelian
    {
        return DB::transaction(function () use ($orderId) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            if ($invoice = $order->invoice()->first()) {
                return $invoice;
            }
            // Repeated barang rows in a PO are one combined obligation. They cannot
            // each consume the same received quantity independently.
            $ordered = $order->details()->get()->groupBy('id_barang')->map->sum('kuantitas');
            if ($ordered->isEmpty() || $ordered->contains(fn ($quantity) => $quantity < 1)) {
                return null;
            }
            $good = DetailPenerimaanBarang::whereHas('penerimaanBarang', fn ($query) => $query->where('id_purchase_order', $orderId))
                ->where('kondisi', 'Baik')->get()->groupBy('id_barang')->map->sum('kuantitas');
            foreach ($ordered as $barangId => $quantity) {
                if (($good[$barangId] ?? 0) < $quantity) {
                    return null;
                }
            }
            $date = $order->penerimaanBarang()->max('tanggal_penerimaan');

            return InvoicePembelian::create([
                'id_purchase_order' => $orderId,
                'tanggal_invoice' => $date,
                'jatuh_tempo' => CarbonImmutable::parse($date)->addDays(30)->toDateString(),
                'total_invoice' => $order->total_harga,
                'status' => 'Belum Lunas',
                'dibuat_otomatis' => true,
            ]);
        }, 5);
    }
}
