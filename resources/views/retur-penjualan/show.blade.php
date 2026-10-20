@extends('layouts.app')
@section('title', 'Detail Retur Penjualan')
@section('content')
    <a class="back-link" href="{{ route('retur-penjualan.index') }}"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Retur Penjualan</a>
    <section class="page-heading"><div><span class="eyebrow">SALES</span><h1>{{ $document->no_retur }}</h1><p>Detail retur penjualan</p></div></section>
    <section class="surface-card form-card">@include('sales.partials.detail-fields', ['fields' => ['no_retur' => 'Nomor Retur', 'invoicePenjualan.no_invoice' => 'Invoice Penjualan', 'invoicePenjualan.salesOrder.no_sales_order' => 'Sales Order', 'invoicePenjualan.salesOrder.customer.nama_customer' => 'Supplier', 'tanggal_retur' => 'Tanggal Retur', 'alasan' => 'Alasan', 'status' => 'Status']])<a href="{{ route('invoice-penjualan.show', $document->id_invoice_penjualan) }}">Lihat Invoice <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
@endsection
