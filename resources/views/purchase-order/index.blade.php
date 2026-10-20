@extends('layouts.app')
@section('title', 'Purchase Order')
@section('content')
    @include('purchasing.partials.list', ['routeName' => 'purchase-order', 'title' => 'Purchase Order', 'description' => 'Kelola order pembelian dan rincian barang dari supplier.', 'icon' => 'bag-check', 'columns' => ['no_order_pembelian' => 'Nomor PO', 'permintaanPembelian.no_faktur' => 'Permintaan', 'supplier.nama_supplier' => 'Supplier', 'tanggal' => 'Tanggal', 'status' => 'Status', 'jumlah' => 'Jumlah', 'total_harga' => 'Total Harga'], 'moneyFields' => ['total_harga']])
@endsection
