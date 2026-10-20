<?php

namespace App\Services;

use App\Http\Requests\StoreReturPembelianRequest;
use App\Http\Requests\UpdateReturPembelianStatusRequest;
use App\Models\DetailReturPembelian;
use App\Models\PenerimaanBarang;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\User;
use App\Support\PurchaseReturnAmounts;
use App\Support\PurchasingAmounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReturPembelianService
{
    private function actor(int $id): void
    {
        abort_unless(User::whereIn('role', ['Admin', 'Purchasing'])->whereKey($id)->exists(), 403);
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function create(array $data, int $actor): ReturPembelian
    {
        $this->actor($actor);
        $data = Validator::make($data, (new StoreReturPembelianRequest)->rules())->validate();
        $orderId = PenerimaanBarang::findOrFail($data['id_penerimaan'])->id_purchase_order;

        return DB::transaction(function () use ($data, $actor, $orderId) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            $receipt = PenerimaanBarang::lockForUpdate()->findOrFail($data['id_penerimaan']);
            if ($receipt->id_purchase_order != $orderId) {
                $this->reject('id_penerimaan', 'PO penerimaan berubah. Muat ulang dokumen.');
            }
            if ($data['tanggal_retur'] < $receipt->tanggal_penerimaan) {
                $this->reject('tanggal_retur', 'Tanggal retur tidak boleh sebelum penerimaan.');
            }
            $sources = $receipt->details()->orderBy('id_detail_penerimaan')->lockForUpdate()->get()->keyBy('id_detail_penerimaan');
            $poLines = $order->details()->orderBy('id_detail_purchase_order')->lockForUpdate()->get()->keyBy('id_detail_purchase_order');
            $active = DetailReturPembelian::whereHas('returPembelian', fn ($q) => $q->where('status', '!=', 'Ditolak'));
            $reserved = (clone $active)->whereIn('id_detail_penerimaan', $sources->keys())->get()->groupBy('id_detail_penerimaan')->map->sum('kuantitas')->all();
            // Original receipts have no PO-line allocation. Require an explicit line,
            // and prevent original damaged returns consuming it beyond ordered qty.
            $rootReserved = (clone $active)->whereIn('id_detail_purchase_order', $poLines->keys())
                ->whereHas('detailPenerimaan', fn ($q) => $q->whereNull('id_detail_retur_asal'))
                ->get()->groupBy('id_detail_purchase_order')->map->sum('kuantitas')->all();
            $poNet = $poLines->sum(fn ($line) => PurchaseReturnAmounts::cents($line->total_harga));
            $poTax = PurchaseReturnAmounts::cents($order->ppn);
            $lines = [];
            $seen = [];
            $net = 0;
            $discount = 0;
            $tax = 0;
            $quantity = 0;
            foreach ($data['items'] as $index => $item) {
                $source = $sources->get($item['id_detail_penerimaan']);
                $poLine = $poLines->get($item['id_detail_purchase_order']);
                if (! $source || $source->kondisi !== 'Rusak') {
                    $this->reject("items.$index.id_detail_penerimaan", 'Pilih detail Rusak dari penerimaan ini.');
                }
                if (! $poLine || $poLine->id_barang != $source->id_barang) {
                    $this->reject("items.$index.id_detail_purchase_order", 'Detail PO harus sesuai barang dan PO penerimaan.');
                }
                $key = $source->getKey().':'.$poLine->getKey();
                if (isset($seen[$key])) {
                    $this->reject("items.$index.kuantitas", 'Gabungkan kuantitas untuk pasangan detail penerimaan/PO yang sama.');
                }
                $seen[$key] = true;
                $qty = (int) $item['kuantitas'];
                $reserved[$source->getKey()] = ($reserved[$source->getKey()] ?? 0) + $qty;
                if ($reserved[$source->getKey()] > $source->kuantitas || $qty > $poLine->kuantitas) {
                    $this->reject("items.$index.kuantitas", 'Kuantitas melebihi barang Rusak yang masih dapat diretur atau alokasi baris PO.');
                }
                if ($source->id_detail_retur_asal) {
                    $origin = $source->returAsal;
                    if (! $origin || $origin->id_detail_purchase_order != $poLine->getKey()) {
                        $this->reject("items.$index.id_detail_purchase_order", 'Retur ulang wajib memakai baris PO asal penggantian.');
                    }
                } else {
                    $rootReserved[$poLine->getKey()] = ($rootReserved[$poLine->getKey()] ?? 0) + $qty;
                    if ($rootReserved[$poLine->getKey()] > $poLine->kuantitas) {
                        $this->reject("items.$index.kuantitas", 'Alokasi retur penerimaan awal melebihi kuantitas baris PO.');
                    }
                }
                $amounts = PurchaseReturnAmounts::line($poLine, $qty, $poNet, $poTax);
                $lines[] = $amounts + ['id_detail_penerimaan' => $source->getKey(), 'id_detail_purchase_order' => $poLine->getKey(), 'sku' => $poLine->sku, 'nama_barang' => $poLine->nama_barang, 'kuantitas' => $qty, 'alasan' => $item['alasan']];
                $net += PurchaseReturnAmounts::cents($amounts['subtotal']);
                $discount += PurchaseReturnAmounts::cents($amounts['diskon']);
                $tax += PurchaseReturnAmounts::cents($amounts['ppn']);
                $quantity += $qty;
            }
            if ($net + $tax > PurchasingAmounts::MAX || $discount > PurchasingAmounts::MAX || $quantity > 2147483647) {
                $this->reject('items', 'Total retur melebihi kapasitas database.');
            }
            $document = ReturPembelian::create(['id_penerimaan' => $receipt->getKey(), 'id_invoice_pembelian' => $order->invoice()->value('id_invoice_pembelian'),
                'tanggal_retur' => $data['tanggal_retur'], 'alasan' => $data['alasan'], 'status' => 'Diajukan', 'created_by' => $actor,
                'subtotal' => PurchasingAmounts::decimal($net), 'diskon' => PurchasingAmounts::decimal($discount), 'ppn' => PurchasingAmounts::decimal($tax), 'total_retur' => PurchasingAmounts::decimal($net + $tax)]);
            foreach ($lines as $line) {
                $document->details()->create($line);
            }

            return $document;
        }, 5);
    }

    public function transition(ReturPembelian $document, array $data, int $actor): ReturPembelian
    {
        $this->actor($actor);
        $data = Validator::make($data, (new UpdateReturPembelianStatusRequest)->rules())->validate();
        if (! $document->id_penerimaan) {
            $this->reject('document', 'Retur historis tanpa penerimaan tetap read-only.');
        }
        $orderId = $document->penerimaanBarang->id_purchase_order;

        return DB::transaction(function () use ($document, $data, $actor, $orderId) {
            PurchaseOrder::lockForUpdate()->findOrFail($orderId);
            $locked = ReturPembelian::lockForUpdate()->findOrFail($document->getKey());
            $allowed = ['Diajukan' => ['Disetujui', 'Ditolak'], 'Disetujui' => ['Selesai']];
            if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                $this->reject('status', 'Perpindahan status retur tidak diperbolehkan.');
            }
            if ($data['status'] === 'Disetujui') {
                $locked->approved_by = $actor;
            }
            if ($data['status'] === 'Selesai') {
                if ($data['tanggal_dikirim'] < $locked->tanggal_retur) {
                    $this->reject('tanggal_dikirim', 'Tanggal pengiriman tidak boleh sebelum tanggal retur.');
                }
                $locked->tanggal_dikirim = $data['tanggal_dikirim'];
                $locked->shipped_by = $actor;
            }
            $locked->status = $data['status'];
            $locked->save();

            return $locked;
        }, 5);
    }
}
