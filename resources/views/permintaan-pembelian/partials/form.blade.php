@php
    $fields = [
        'no_faktur' => ['label' => 'Nomor Faktur / Permintaan', 'readonly' => true, 'default' => $documentNumber ?? ''],
        'status' => ['label' => 'Status', 'type' => 'select', 'options' => \App\Support\PurchasingStatuses::options(\App\Support\PurchasingStatuses::REQUEST), 'default' => 'Draft'],
        'tanggal' => ['label' => 'Tanggal', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
        'tanggal_diperlukan' => ['label' => 'Tanggal Diperlukan', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
        'pemohon' => ['label' => 'Pemohon', 'type' => 'display', 'value' => isset($document) ? ($document->pemohon?->name ?? 'Pengguna lama tidak tersedia') : auth()->user()->name, 'help' => 'Pemohon ditetapkan dari akun login saat dibuat dan tidak dapat diganti.'],
        'id_penyetuju' => ['label' => 'Penyetuju', 'type' => 'select', 'options' => $approvers->pluck('name', 'id')],
    ];
@endphp
<section class="page-heading"><h1>{{ isset($document) ? 'Edit' : 'Tambah' }} Permintaan Pembelian</h1></section>
<form method="POST" action="{{ isset($document) ? route('permintaan-pembelian.update', $document) : route('permintaan-pembelian.store') }}" class="surface-card form-card">
@csrf @isset($document) @method('PUT') @endisset
@include('purchasing.partials.fields')
<h2 class="h5 mt-4">Detail barang</h2>
@if(isset($document) && $document->details->isEmpty())<p>Dokumen lama belum memiliki detail barang. Tambahkan detail jika informasinya tersedia.</p>@endif
@php($lineItems = old('items', isset($document) ? $document->details->toArray() : [['id_barang' => '', 'kuantitas' => 1, 'tanggal_dibutuhkan' => now('Asia/Jakarta')->toDateString()]]))
<div class="table-responsive"><table class="table"><thead><tr><th>SKU / Nama Barang</th><th>Kuantitas</th><th>Tanggal Dibutuhkan</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody id="request-items">
@foreach($lineItems as $index => $item) @include('permintaan-pembelian.partials.item-row') @endforeach
</tbody></table></div>
<button type="button" id="add-request-item" class="btn btn-outline-primary">Tambah Barang</button>
<div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('permintaan-pembelian.index') }}">Batal</a><button class="btn btn-primary">Simpan Permintaan Pembelian</button></div></form>
<template id="request-item-template">@include('permintaan-pembelian.partials.item-row', ['index' => '__INDEX__', 'item' => ['id_barang' => '', 'kuantitas' => 1]])</template>
@push('scripts')<script src="{{ asset('assets/js/purchase-request.js') }}" defer></script>@endpush
