<?php

namespace App\Services;

use App\Models\DetailPenerimaanBarang;
use App\Models\DetailReturPembelian;
use App\Models\PenerimaanBarang;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenerimaanBarangService
{
    public function save(array $data, int $userId, ?PenerimaanBarang $receipt = null): PenerimaanBarang
    {
        return DB::transaction(function () use ($data, $userId, $receipt) {
            // Lock the PO first for receipt create/update/delete and invoice creation.
            $order = PurchaseOrder::lockForUpdate()->findOrFail($data['id_purchase_order']);
            if ($receipt) {
                if (array_filter(array_column($data['items'], 'id_detail_retur_asal'))) {
                    throw ValidationException::withMessages(['document' => 'Pengganti wajib dicatat sebagai dokumen penerimaan baru.']);
                }
                $receipt = PenerimaanBarang::lockForUpdate()->findOrFail($receipt->getKey());
                if ($receipt->id_purchase_order != $order->getKey()) {
                    throw ValidationException::withMessages(['id_purchase_order' => 'PO penerimaan tidak dapat diganti.']);
                }
                $this->ensureMutable($order, $receipt);
            }
            if ($data['tanggal_penerimaan'] < $order->tanggal) {
                throw ValidationException::withMessages(['tanggal_penerimaan' => 'Tanggal penerimaan tidak boleh sebelum tanggal PO.']);
            }
            $ordered = $order->details()->get()->groupBy('id_barang')->map->sum('kuantitas');
            $received = DetailPenerimaanBarang::whereHas('penerimaanBarang', fn ($q) => $q->where('id_purchase_order', $order->getKey()))
                ->whereNull('id_detail_retur_asal')
                ->when($receipt, fn ($q) => $q->where('id_penerimaan', '!=', $receipt->getKey()))
                ->get()->groupBy('id_barang')->map->sum('kuantitas')->all();
            $replacementIds = array_filter(array_column($data['items'], 'id_detail_retur_asal'));
            $origins = DetailReturPembelian::with(['returPembelian.penerimaanBarang', 'detailPenerimaan'])->whereIn('id_detail_retur', $replacementIds)->orderBy('id_detail_retur')->lockForUpdate()->get()->keyBy('id_detail_retur');
            $replaced = DetailPenerimaanBarang::whereIn('id_detail_retur_asal', $replacementIds)->get()->groupBy('id_detail_retur_asal')->map->sum('kuantitas')->all();
            foreach ($data['items'] as $index => $item) {
                $id = $item['id_barang'];
                if (! $ordered->has($id)) {
                    throw ValidationException::withMessages(["items.$index.id_barang" => 'Barang harus berasal dari detail PO.']);
                }
                $originId = $item['id_detail_retur_asal'] ?? null;
                if ($originId) {
                    $origin = $origins->get($originId);
                    if (! $origin || $origin->returPembelian->status !== 'Selesai' || $origin->returPembelian->penerimaanBarang?->id_purchase_order != $order->getKey() || $origin->detailPenerimaan->id_barang != $id) {
                        throw ValidationException::withMessages(["items.$index.id_detail_retur_asal" => 'Pengganti harus merujuk detail retur Selesai untuk barang dan PO yang sama.']);
                    }
                    if (! $origin->returPembelian->tanggal_dikirim || $data['tanggal_penerimaan'] < $origin->returPembelian->tanggal_dikirim) {
                        throw ValidationException::withMessages(['tanggal_penerimaan' => 'Tanggal pengganti tidak boleh sebelum pengiriman retur.']);
                    }
                    $replaced[$originId] = ($replaced[$originId] ?? 0) + $item['kuantitas'];
                    if ($replaced[$originId] > $origin->kuantitas) {
                        throw ValidationException::withMessages(["items.$index.kuantitas" => 'Kuantitas pengganti melebihi sisa kuota detail retur.']);
                    }
                } else {
                    $received[$id] = ($received[$id] ?? 0) + $item['kuantitas'];
                    if ($received[$id] > $ordered[$id]) {
                        throw ValidationException::withMessages(["items.$index.kuantitas" => 'Total penerimaan barang melebihi kuantitas PO.']);
                    }
                }
            }
            $receipt ??= new PenerimaanBarang;
            $receipt->fill(['id_purchase_order' => $order->getKey(), 'tanggal_penerimaan' => $data['tanggal_penerimaan'],
                'id_penerima' => $receipt->exists ? $receipt->id_penerima : $userId])->save();
            $receipt->details()->delete();
            foreach ($data['items'] as $item) {
                $receipt->details()->create($item);
            }

            app(AutomaticPurchaseInvoiceService::class)->createIfComplete($order->getKey());

            return $receipt;
        }, 5);
    }

    public function delete(PenerimaanBarang $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($receipt->id_purchase_order);
            $locked = PenerimaanBarang::lockForUpdate()->findOrFail($receipt->getKey());
            $this->ensureMutable($order, $locked);
            $locked->delete();
        });
    }

    private function ensureMutable(PurchaseOrder $order, PenerimaanBarang $receipt): void
    {
        if ($receipt->returPembelian()->exists() || $receipt->details()->whereNotNull('id_detail_retur_asal')->exists()) {
            throw ValidationException::withMessages(['document' => 'Penerimaan yang memiliki retur atau merupakan pengganti tidak dapat diubah/dihapus.']);
        }
        if ($order->invoices()->exists()) {
            throw ValidationException::withMessages(['document' => 'Penerimaan yang PO-nya sudah memiliki invoice tidak dapat diubah atau dihapus.']);
        }
    }
}
