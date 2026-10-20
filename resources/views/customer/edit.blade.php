@extends('layouts.app')
@section('title', 'Edit Customer')
@section('content')
<a href="{{ route('customer.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Customer</a>
<section class="page-heading"><div><span class="eyebrow">MASTER DATA / CUSTOMER</span><h1>Edit Customer</h1><p>Perbarui informasi {{ $customer->nama_customer }}.</p></div></section>
<div class="row g-4"><div class="col-xl-8"><section class="surface-card form-card"><div class="card-heading"><div><h2>Informasi Customer</h2><p>Kolom bertanda <span class="text-danger">*</span> wajib diisi.</p></div><span class="stat-icon blue"><i class="bi bi-pencil-square" aria-hidden="true"></i></span></div><form action="{{ route('customer.update', $customer->id_customer) }}" method="POST">@csrf @method('PUT')
    @include('customer.partials.form')
    <div class="form-actions"><a href="{{ route('customer.index') }}" class="btn btn-outline-secondary">Batal</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Simpan Perubahan</button></div>
</form></section></div><div class="col-xl-4">@include('customer.partials.form-guide')</div></div>
@endsection
