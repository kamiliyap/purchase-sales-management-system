@extends('layouts.app')
@section('title', 'Sales Order')
@section('content')
    @include('sales.partials.list', ['routeName' => 'sales-order', 'title' => 'Sales Order', 'description' => 'Kelola order penjualan dan rincian barang untuk customer.', 'icon' => 'cart3', 'columns' => ['no_sales_order' => 'Nomor SO', 'customer.nama_customer' => 'Customer', 'tanggal' => 'Tanggal', 'status' => 'Status', 'total_harga' => 'Total Harga'], 'moneyFields' => ['total_harga']])
@endsection
