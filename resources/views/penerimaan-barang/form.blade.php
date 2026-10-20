@extends('layouts.app')
@section('title', 'Form Penerimaan Barang')
@section('content')
<section class="page-heading"><h1>Penerimaan Barang</h1></section>
@if(! $document->exists)
<form method="GET" action="{{ route('penerimaan-barang.create') }}" class="surface-card form-card mb-4"><label for="po" class="form-label">Pilih Purchase Order</label><select id="po" name="purchase_order" class="form-select" required><option value="">Pilih PO</option>@foreach($orders as $choice)<option value="{{ $choice->getKey() }}" @selected($order?->getKey() == $choice->getKey())>{{ $choice->no_order_pembelian }}</option>@endforeach</select>@if($returnChoices->isNotEmpty())<label for="return-source" class="form-label mt-3">Jenis Penerimaan</label><select id="return-source" name="retur" class="form-select"><option value="">Penerimaan awal / normal</option>@foreach($returnChoices as $choice)<option value="{{ $choice->getKey() }}" @selected($replacementReturn?->getKey() == $choice->getKey())>Pengganti untuk {{ $choice->no_retur }}</option>@endforeach</select>@endif<button class="btn btn-outline-primary mt-3">Ambil Barang PO</button></form>
@endif
@if($order)
<form method="POST" action="{{ $document->exists ? route('penerimaan-barang.update', $document) : route('penerimaan-barang.store') }}" class="surface-card form-card">
@csrf @if($document->exists) @method('PUT') @endif
<input type="hidden" name="id_purchase_order" value="{{ $order->getKey() }}">
@if($replacementReturn)<div class="alert alert-info">Penerimaan pengganti untuk retur <a href="{{ route('retur-pembelian.show',$replacementReturn) }}">{{ $replacementReturn->no_retur }}</a>. Kuota mengikuti sisa setiap detail retur Selesai. Dokumen ini disimpan sebagai bukti baru.</div>@endif
<p>Purchase Order: <strong>{{ $order->no_order_pembelian }}</strong></p>
<label class="form-label" for="number">No Penerimaan</label><input id="number" class="form-control mb-3" readonly value="{{ $documentNumber }}">
<label class="form-label" for="date">Tanggal Penerimaan</label><input id="date" class="form-control mb-3" type="date" name="tanggal_penerimaan" min="{{ $order->tanggal }}" value="{{ old('tanggal_penerimaan', $document->tanggal_penerimaan ?? now('Asia/Jakarta')->toDateString()) }}" required>
<p>Penerima: {{ $document->exists ? $document->penerima->name : auth()->user()->name }}</p>
<p class="text-secondary">Hapus baris yang tidak diterima. Kuantitas Baik dan Rusak dihitung bersama terhadap batas PO. Kondisi Baik menyatakan barang telah diverifikasi. Invoice dibuat otomatis saat seluruh kuantitas PO berkondisi Baik.</p>
@php
$rows = old('items', $document->exists ? $document->details->toArray() : ($replacementRows ?? $order->details->groupBy('id_barang')->map(fn($group) => ['id_barang' => $group->first()->id_barang, 'kuantitas' => $group->sum('kuantitas'), 'kondisi' => 'Baik'])->values()->toArray()));
@endphp
<div class="table-responsive"><table class="table"><thead><tr><th>SKU / Nama Barang</th><th>Kuantitas</th><th>Kondisi</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody id="receipt-items">
@foreach($rows as $index => $item)
@php($poItem = $order->details->firstWhere('id_barang', $item['id_barang'] ?? null))
<tr><td>@if(!empty($item['id_detail_retur_asal']))<input type="hidden" name="items[{{ $index }}][id_detail_retur_asal]" value="{{ $item['id_detail_retur_asal'] }}"><small>Pengganti detail retur #{{ $item['id_detail_retur_asal'] }}</small><br>@endif{{ $poItem?->sku }} / {{ $poItem?->nama_barang }}<input type="hidden" name="items[{{ $index }}][id_barang]" value="{{ $item['id_barang'] ?? '' }}"></td><td><input aria-label="Kuantitas" class="form-control" type="number" min="1" name="items[{{ $index }}][kuantitas]" value="{{ $item['kuantitas'] ?? '' }}" required></td><td><select aria-label="Kondisi" class="form-select" name="items[{{ $index }}][kondisi]">@foreach(['Baik', 'Rusak'] as $condition)<option @selected(($item['kondisi'] ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></td><td><input aria-label="Keterangan" class="form-control" maxlength="1000" name="items[{{ $index }}][keterangan]" value="{{ $item['keterangan'] ?? '' }}"></td><td><button type="button" class="btn btn-outline-secondary" data-split>Salin baris</button><button type="button" class="btn btn-outline-danger" data-remove>Hapus</button></td></tr>
@endforeach
</tbody></table></div><p class="text-secondary">Salin baris untuk memisahkan kondisi Baik dan Rusak pada barang yang sama.</p>
@if(count($rows))<button class="btn btn-primary">Simpan Penerimaan</button>@else<p class="text-secondary">Kuota pengganti untuk retur ini sudah terpenuhi.</p>@endif</form>
@endif
<a class="btn btn-link" href="{{ route('penerimaan-barang.index') }}">Kembali</a>
@endsection
@push('scripts')<script src="{{ asset('assets/js/penerimaan-barang.js') }}" defer></script>@endpush
