@extends('layouts.app')
@section('title', 'Retur Pembelian')
@section('content')
    @include('purchasing.partials.list', ['routeName' => 'retur-pembelian', 'title' => 'Retur Pembelian', 'description' => 'Retur barang Rusak berdasarkan Penerimaan Barang.', 'icon' => 'box-arrow-left', 'columns' => ['no_retur' => 'Nomor Retur', 'penerimaanBarang.no_penerimaan' => 'Penerimaan', 'invoicePembelian.no_invoice' => 'Invoice Historis/Referensi', 'tanggal_retur' => 'Tanggal Retur', 'alasan' => 'Alasan', 'status' => 'Status'], 'moneyFields' => []])
@endsection
