@extends('layouts.app')
@section('title', 'Tambah Supplier')
@section('content')
<a href="{{ route('supplier.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Supplier</a>
<section class="page-heading"><div><span class="eyebrow">MASTER DATA / SUPPLIER</span><h1>Tambah Supplier</h1><p>Lengkapi informasi mitra pemasok baru Anda.</p></div></section>
<div class="row g-4"><div class="col-xl-8"><section class="surface-card form-card"><div class="card-heading"><div><h2>Informasi Supplier</h2><p>Kolom bertanda <span class="text-danger">*</span> wajib diisi.</p></div><span class="stat-icon blue"><i class="bi bi-truck" aria-hidden="true"></i></span></div><form action="{{ route('supplier.store') }}" method="POST">@csrf
    @include('supplier.partials.form')
    <div class="form-actions"><a href="{{ route('supplier.index') }}" class="btn btn-outline-secondary">Batal</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Simpan Supplier</button></div>
</form></section></div><div class="col-xl-4">@include('supplier.partials.form-guide')</div></div>
@endsection
