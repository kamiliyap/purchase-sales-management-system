@extends('layouts.app')
@section('title', 'Invoice Pembelian')
@section('content')
    @include('purchasing.partials.list', ['routeName' => 'invoice-pembelian', 'title' => 'Invoice Pembelian', 'description' => 'Invoice dibuat otomatis setelah seluruh barang PO diterima dan diverifikasi Baik.', 'icon' => 'receipt', 'columns' => ['no_invoice' => 'Nomor Invoice', 'purchaseOrder.no_order_pembelian' => 'Purchase Order', 'nomor_penerimaan' => 'Penerimaan', 'sumber' => 'Sumber', 'tanggal_invoice' => 'Tanggal', 'jatuh_tempo' => 'Jatuh Tempo', 'total_invoice' => 'Total Invoice', 'status' => 'Status'], 'moneyFields' => ['total_invoice']])
@endsection
