@extends('layouts.app')
@section('title', 'Invoice Penjualan')
@section('content')
    @include('sales.partials.list', ['routeName' => 'invoice-penjualan', 'title' => 'Invoice Penjualan', 'description' => 'Catat tagihan berdasarkan Sales Order.', 'icon' => 'receipt', 'columns' => ['no_invoice' => 'Nomor Invoice', 'salesOrder.no_sales_order' => 'Sales Order', 'tanggal_invoice' => 'Tanggal', 'jatuh_tempo' => 'Jatuh Tempo', 'total_invoice' => 'Total Invoice', 'status' => 'Status'], 'moneyFields' => ['total_invoice']])
@endsection
