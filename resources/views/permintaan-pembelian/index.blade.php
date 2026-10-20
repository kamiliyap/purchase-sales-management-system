@extends('layouts.app')
@section('title', 'Permintaan Pembelian')
@section('content')
    @include('purchasing.partials.list', ['routeName' => 'permintaan-pembelian', 'title' => 'Permintaan Pembelian', 'description' => 'Kelola pengajuan kebutuhan pembelian bisnis Anda.', 'icon' => 'clipboard-check', 'columns' => ['no_faktur' => 'Nomor Permintaan', 'tanggal' => 'Tanggal', 'tanggal_diperlukan' => 'Diperlukan', 'pemohon.name' => 'Pemohon', 'penyetuju.name' => 'Penyetuju', 'status' => 'Status'], 'moneyFields' => []])
@endsection
