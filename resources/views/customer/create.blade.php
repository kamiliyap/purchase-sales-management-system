@extends('layouts.app')
@section('title', 'Tambah Customer')
@section('content')
<a href="{{ route('customer.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Customer</a>
<section class="page-heading"><div><span class="eyebrow">MASTER DATA / CUSTOMER</span><h1>Tambah Customer</h1><p>Lengkapi informasi mitra pelanggan baru Anda.</p></div></section>
<div class="row g-4"><div class="col-xl-8"><section class="surface-card form-card"><div class="card-heading"><div><h2>Informasi Customer</h2><p>Kolom bertanda <span class="text-danger">*</span> wajib diisi.</p></div><span class="stat-icon blue"><i class="bi bi-people" aria-hidden="true"></i></span></div><form action="{{ route('customer.store') }}" method="POST">@csrf
    @include('customer.partials.form')
    <div class="form-actions"><a href="{{ route('customer.index') }}" class="btn btn-outline-secondary">Batal</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Simpan Customer</button></div>
</form></section></div><div class="col-xl-4">@include('customer.partials.form-guide')</div></div>
@endsection
