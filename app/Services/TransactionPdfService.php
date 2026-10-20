<?php

namespace App\Services;

use App\Models\InvoicePembelian;
use App\Models\InvoicePenjualan;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use Barryvdh\DomPDF\Facade\Pdf;

class TransactionPdfService
{
    private const DOCUMENTS = [
        'permintaan-pembelian' => [PermintaanPembelian::class, ['pemohon', 'penyetuju', 'details'], 'no_faktur', 'Permintaan Pembelian'],
        'purchase-order' => [PurchaseOrder::class, ['supplier', 'details', 'invoices'], 'no_order_pembelian', 'Purchase Order'],
        'penerimaan-barang' => [PenerimaanBarang::class, ['purchaseOrder.details', 'details', 'penerima'], 'no_penerimaan', 'Penerimaan Barang'],
        'invoice-pembelian' => [InvoicePembelian::class, ['purchaseOrder.supplier', 'purchaseOrder.details', 'purchaseOrder.penerimaanBarang'], 'no_invoice', 'Invoice Pembelian'],
        'retur-pembelian' => [ReturPembelian::class, ['penerimaanBarang.purchaseOrder.supplier', 'invoicePembelian.purchaseOrder.supplier', 'details', 'pembuat', 'penyetuju', 'pengirim'], 'no_retur', 'Retur Pembelian'],
        'invoice-penjualan' => [InvoicePenjualan::class, ['salesOrder.customer', 'salesOrder.details'], 'no_invoice', 'Invoice Penjualan'],
    ];

    public function data(string $type, int $id): array
    {
        abort_unless(isset(self::DOCUMENTS[$type]), 404);
        [$class, $relations, $numberField, $title] = self::DOCUMENTS[$type];
        $document = $class::with($relations)->findOrFail($id);
        $number = $document->getAttribute($numberField);
        $receiptRows = null;
        if ($type === 'penerimaan-barang') {
            foreach (['Nomor Penerimaan' => $number, 'Nomor PO' => $document->purchaseOrder?->no_order_pembelian] as $label => $value) {
                abort_if(! is_string($value) || trim($value) === '' || preg_match('/^[\s0\/._-]+$/D', $value), 422, $label.' tidak valid pada database (nilai tersimpan: '.(is_string($value) && trim($value) !== '' ? $value : 'kosong').'). Tinjau data sumber sebelum mencetak.');
            }
            $receiptRows = $document->details->groupBy(fn ($line) => json_encode([$line->id_barang, $line->keterangan]))->map(function ($lines) use ($document) {
                $first = $lines->first();
                $snapshot = $document->purchaseOrder->details->firstWhere('id_barang', $first->id_barang);
                $good = 0;
                $damaged = 0;
                foreach ($lines as $line) {
                    abort_unless(in_array($line->kondisi, ['Baik', 'Rusak'], true) && $line->kuantitas > 0, 422, 'Detail penerimaan tidak valid. Tinjau data sumber sebelum mencetak.');
                    if ($line->kondisi === 'Baik') {
                        $good += $line->kuantitas;
                    } else {
                        $damaged += $line->kuantitas;
                    }
                }

                return ['sku' => $snapshot?->sku, 'nama_barang' => $snapshot?->nama_barang, 'kuantitas' => $good + $damaged, 'baik' => $good, 'rusak' => $damaged, 'keterangan' => $first->keterangan];
            })->values();
        }
        $date = $document->tanggal ?? $document->tanggal_penerimaan ?? $document->tanggal_invoice ?? $document->tanggal_retur;
        $company = config('company');
        $logo = null;
        if ($company['logo']) {
            $path = realpath(public_path($company['logo']));
            $public = realpath(public_path());
            if ($path && str_starts_with(strtolower($path), strtolower($public.DIRECTORY_SEPARATOR)) && is_file($path) && in_array(mime_content_type($path), ['image/png', 'image/jpeg'], true)) {
                $logo = $path;
            }
        }

        return compact('document', 'number', 'date', 'title', 'company', 'logo', 'receiptRows');
    }

    public function render(string $type, int $id): array
    {
        $data = $this->data($type, $id);
        $pdf = Pdf::loadView('pdf.'.$type, $data)->setPaper('a4', 'portrait')->setOptions([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'chroot' => public_path(),
        ]);
        $filename = $type.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $data['number']).'.pdf';

        return [$pdf, $filename];
    }
}
