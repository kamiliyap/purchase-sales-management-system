@extends('layouts.app')

@section('title', 'Detail Customer')

@section('content')
    <a href="{{ route('customer.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Customer</a>
    <section class="page-heading"><div><span class="eyebrow">MASTER DATA / CUSTOMER</span><h1>Detail Customer</h1><p>{{ $customer->nama_customer }}</p></div><a href="{{ route('customer.edit', $customer->id_customer) }}" class="btn btn-primary"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit Customer</a></section>
    <section class="surface-card form-card"><div class="card-heading"><h2>Informasi Customer</h2><span class="stat-icon violet"><i class="bi bi-people" aria-hidden="true"></i></span></div><dl class="row mb-0">
        @foreach (['nama_customer' => 'Nama Customer', 'alamat' => 'Alamat', 'telepon' => 'Telepon', 'email' => 'Email'] as $field => $label)
            <dt class="col-sm-3 mb-2">{{ $label }}</dt><dd class="col-sm-9 mb-4 text-break">{{ $customer->$field }}</dd>
        @endforeach
    </dl></section>
@endsection
