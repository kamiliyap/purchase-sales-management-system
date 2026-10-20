@extends('layouts.app')
@section('title', 'Detail Permintaan Pembelian')
@section('content')
    @include('pdf.buttons', ['pdfModule' => 'permintaan-pembelian'])
    <a class="back-link" href="{{ route('permintaan-pembelian.index') }}"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Permintaan Pembelian</a>
    <section class="page-heading"><div><span class="eyebrow">PURCHASING</span><h1>{{ $document->no_faktur }}</h1><p>Detail permintaan pembelian</p></div><a class="btn btn-primary" href="{{ route('permintaan-pembelian.edit', $document->getKey()) }}">Edit Permintaan</a></section>
    <section class="surface-card form-card">@include('purchasing.partials.detail-fields', ['fields' => ['no_faktur' => 'Nomor Faktur / Permintaan', 'tanggal' => 'Tanggal', 'tanggal_diperlukan' => 'Tanggal Diperlukan', 'pemohon.name' => 'Pemohon', 'penyetuju.name' => 'Penyetuju', 'status' => 'Status']])</section>
    <section class="surface-card form-card mt-4"><h2 class="h5">Detail Barang</h2>@include('permintaan-pembelian.partials.details')</section>
    @if($document->status === 'Disetujui') @can('access-route', 'purchase-order.create')<a class="btn btn-outline-primary mt-3" href="{{ route('purchase-order.create', ['id_permintaan' => $document->getKey()]) }}">Buat PO dari Detail Permintaan</a>@endcan @endif
@endsection
