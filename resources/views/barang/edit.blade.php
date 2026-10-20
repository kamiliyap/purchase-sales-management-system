@extends('layouts.app')

@section('title', 'Edit Barang')

@section('content')
    <a href="{{ route('barang.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Barang</a>
    <section class="page-heading"><div><span class="eyebrow">MASTER DATA / BARANG</span><h1>Edit Barang</h1><p>Perbarui informasi {{ $barang->nama_barang }}.</p></div></section>
    <div class="row g-4"><div class="col-xl-8"><section class="surface-card form-card">
        <div class="card-heading"><div><h2>Informasi Barang</h2><p>Kolom bertanda <span class="text-danger">*</span> wajib diisi.</p></div><span class="stat-icon teal"><i class="bi bi-pencil-square" aria-hidden="true"></i></span></div>
        <form action="{{ route('barang.update', $barang->id_barang) }}" method="POST">@csrf @method('PUT')
            @include('barang.partials.form')
            <div class="form-actions"><a href="{{ route('barang.index') }}" class="btn btn-outline-secondary">Batal</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Simpan Perubahan</button></div>
        </form>
    </section></div><div class="col-xl-4">@include('barang.partials.form-guide')</div></div>
@endsection
