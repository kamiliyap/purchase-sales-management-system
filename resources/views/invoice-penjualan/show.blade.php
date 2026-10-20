@extends('layouts.app')
@section('title', 'Detail Invoice Penjualan')
@section('content')
    @include('pdf.buttons', ['pdfModule' => 'invoice-penjualan'])
    <a class="back-link" href="{{ route('invoice-penjualan.index') }}"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Invoice Penjualan</a>
    <section class="page-heading"><div><span class="eyebrow">SALES</span><h1>{{ $document->no_invoice }}</h1><p>Detail invoice penjualan</p></div>@can('access-route', 'retur-penjualan.create')<a class="btn btn-primary" href="{{ route('retur-penjualan.create', ['invoice' => $document->getKey()]) }}">Buat Retur</a>@endcan</section>
    <section class="surface-card form-card">@include('sales.partials.detail-fields', ['fields' => ['no_invoice' => 'Nomor Invoice', 'salesOrder.no_sales_order' => 'Sales Order', 'salesOrder.customer.nama_customer' => 'Supplier', 'tanggal_invoice' => 'Tanggal Invoice', 'jatuh_tempo' => 'Jatuh Tempo', 'total_invoice' => 'Total Invoice', 'status' => 'Status'], 'moneyFields' => ['total_invoice']])<a href="{{ route('sales-order.show', $document->id_sales_order) }}">Lihat Sales Order <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
    @if($document->returPenjualan->isNotEmpty())<section class="surface-card form-card mt-4"><h2 class="h6">Retur terkait</h2>@foreach($document->returPenjualan as $retur)<a class="d-block mt-2" href="{{ route('retur-penjualan.show', $retur->getKey()) }}">{{ $retur->no_retur }}</a>@endforeach</section>@endif
@endsection
