@extends('pdf.layout')
@section('body')
<p><strong>Customer:</strong> {{ $document->salesOrder?->customer?->nama_customer ?? 'Customer historis tidak tersedia' }}<br>{{ $document->salesOrder?->customer?->alamat }}</p>
<p><strong>Sales Order:</strong> {{ $document->salesOrder?->no_sales_order ?? 'Tidak tersedia' }}<br><strong>Jatuh Tempo:</strong> {{ $document->jatuh_tempo }}</p>
@if($document->salesOrder)
@include('pdf.order-lines', ['details' => $document->salesOrder->details, 'priceField' => 'harga_jual'])
@include('pdf.totals', ['details' => $document->salesOrder->details, 'total' => $document->total_invoice, 'totalLabel' => 'Total Tagihan Invoice'])
@else<p>Total Tagihan Invoice: {{ \App\Support\PdfAmounts::money($document->total_invoice) }}</p>@endif
<p class="muted">Rincian mengikuti Sales Order terkait. Total tagihan mengikuti nominal invoice tersimpan. Dokumen ini bukan kwitansi atau bukti verifikasi pembayaran.</p>
@include('pdf.signatures', ['signatures' => ['Bagian Keuangan' => null, 'Customer / Mengetahui' => null]])
@endsection
