@extends('pdf.purchasing.layout')
@section('metadata')
<table class="meta"><tr><td><div class="label">Tanggal Invoice</div><div class="value">{{ $document->tanggal_invoice }}</div></td><td><div class="label">Tanggal Jatuh Tempo</div><div class="value">{{ $document->jatuh_tempo }}</div></td></tr><tr><td><div class="label">Supplier</div><div class="value">{{ $document->purchaseOrder?->supplier?->nama_supplier ?? 'Supplier historis tidak tersedia' }}</div><div>{{ $document->purchaseOrder?->supplier?->alamat }}</div>@if($document->purchaseOrder?->supplier?->telepon)<div>Telepon: {{ $document->purchaseOrder->supplier->telepon }}</div>@endif</td><td><div class="label">Nomor Order Pembelian (PO)</div><div class="value">@include('pdf.purchasing.number', ['value' => $document->purchaseOrder?->no_order_pembelian])</div><div class="label" style="margin-top:6px">Termin / Batas Pembayaran</div><div>Pembayaran paling lambat {{ $document->jatuh_tempo }} sesuai jatuh tempo tersimpan.</div><div class="muted">Termin khusus tidak tercatat sebagai data terpisah.</div></td></tr></table>
@endsection
@section('body')
@if($document->dibuat_otomatis)<p class="muted">Invoice dibuat otomatis oleh sistem setelah seluruh barang PO diverifikasi Baik.</p>@endif
@if($document->purchaseOrder)
<p class="muted">Dokumen penerimaan: {{ $document->purchaseOrder->penerimaanBarang->pluck('no_penerimaan')->join(', ') ?: 'Belum tercatat pada dokumen historis' }}</p>
@include('pdf.purchasing.order-lines', ['details' => $document->purchaseOrder->details, 'priceField' => 'harga_unit'])
@include('pdf.totals', ['details' => $document->purchaseOrder->details, 'tax' => $document->purchaseOrder->ppn, 'total' => $document->total_invoice, 'totalLabel' => 'Total Tagihan Invoice'])
@else<p>Total Tagihan Invoice: {{ \App\Support\PdfAmounts::money($document->total_invoice) }}</p>@endif
<div class="notice">Rincian mengikuti PO terkait. Total tagihan mengikuti nominal invoice tersimpan. Dokumen ini bukan kwitansi atau bukti verifikasi pembayaran.</div>
@include('pdf.purchasing.signatures', ['signatures' => ['Bagian Keuangan' => null, 'Mengetahui' => null]])
@endsection
