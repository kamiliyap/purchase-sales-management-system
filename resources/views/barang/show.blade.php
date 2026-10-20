@extends('layouts.app')

@section('title', 'Detail Barang')

@section('content')
    <a href="{{ route('barang.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Barang</a>
    <section class="page-heading"><div><span class="eyebrow">MASTER DATA / BARANG</span><h1>Detail Barang</h1><p>{{ $barang->nama_barang }}</p></div><a href="{{ route('barang.edit', $barang->id_barang) }}" class="btn btn-primary"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit Barang</a></section>
    <section class="surface-card form-card"><div class="card-heading"><h2>Informasi Barang</h2><span class="stat-icon teal"><i class="bi bi-box-seam" aria-hidden="true"></i></span></div><dl class="row mb-0">
        @foreach (['sku' => 'SKU', 'nama_barang' => 'Nama Barang', 'satuan' => 'Satuan'] as $field => $label)
            <dt class="col-sm-3 mb-2">{{ $label }}</dt><dd class="col-sm-9 mb-4 text-break">{{ $barang->$field }}</dd>
        @endforeach
        <dt class="col-sm-3 mb-2">Harga Beli</dt><dd class="col-sm-9 mb-4">Rp {{ number_format($barang->harga_beli, 2, ',', '.') }}</dd>
        <dt class="col-sm-3 mb-2">Stok</dt><dd class="col-sm-9 mb-4">{{ number_format($barang->stok, 0, ',', '.') }}</dd>
    </dl></section>
@endsection
