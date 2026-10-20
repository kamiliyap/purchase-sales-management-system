@extends('layouts.app')
@section('title', 'Retur Penjualan')
@section('content')
    @include('sales.partials.list', ['routeName' => 'retur-penjualan', 'title' => 'Retur Penjualan', 'description' => 'Catat retur berdasarkan invoice penjualan.', 'icon' => 'box-arrow-left', 'columns' => ['no_retur' => 'Nomor Retur', 'invoicePenjualan.no_invoice' => 'Invoice', 'tanggal_retur' => 'Tanggal Retur', 'alasan' => 'Alasan', 'status' => 'Status'], 'moneyFields' => []])
@endsection
