@extends('layouts.app')
@section('title', 'Detail Retur Pembelian')
@section('content')
@include('pdf.buttons',['pdfModule'=>'retur-pembelian'])
<section class="page-heading"><div><h1>{{ $document->no_retur }}</h1><p>{{ $document->tanggal_retur }} / {{ $document->status }}</p></div></section>
<section class="surface-card form-card mb-4"><h2 class="h5">{{ config('company.name') }}</h2>
@if($document->penerimaanBarang)<p>Penerimaan: <a href="{{ route('penerimaan-barang.show',$document->penerimaanBarang) }}">{{ $document->penerimaanBarang->no_penerimaan }}</a> / {{ $document->penerimaanBarang->tanggal_penerimaan }}<br>PO: {{ $document->penerimaanBarang->purchaseOrder->no_order_pembelian }}<br>Supplier: {{ $document->penerimaanBarang->purchaseOrder->supplier?->nama_supplier }}</p>@else<p>Retur historis tanpa relasi penerimaan; data dipertahankan dan read-only.</p>@endif
@if($document->invoicePembelian)<p>Invoice: <a href="{{ route('invoice-pembelian.show',$document->invoicePembelian) }}">{{ $document->invoicePembelian->no_invoice }}</a> / PO {{ $document->invoicePembelian->purchaseOrder->no_order_pembelian }} / {{ $document->invoicePembelian->purchaseOrder->supplier?->nama_supplier }}</p>@else<p>Referensi invoice belum ada saat retur dibuat.</p>@endif
<p>Alasan: {{ $document->alasan }}</p><p>Nilai retur: {{ $document->total_retur === null ? 'Belum tercatat pada histori' : \App\Support\PdfAmounts::money($document->total_retur) }}. Nilai ini bukan refund dan tidak mengurangi invoice.</p>
@include('retur-pembelian.partials.details')
<p>Selesai berarti barang Rusak telah dikirim kembali ke supplier, bukan barang pengganti sudah diterima.</p>
@if($document->tanggal_dikirim)<p>Tanggal dikirim: {{ $document->tanggal_dikirim }} / Pengirim: {{ $document->pengirim?->name }}</p>@endif
@can('access-route','retur-pembelian.update')
@if($document->id_penerimaan && in_array($document->status,['Diajukan','Disetujui']))
<form method="POST" action="{{ route('retur-pembelian.update',$document) }}">@csrf @method('PATCH')<label class="form-label" for="status">Perubahan Status</label><select id="status" name="status" class="form-select mb-3">@foreach($document->status === 'Diajukan' ? ['Disetujui','Ditolak'] : ['Selesai'] as $status)<option>{{ $status }}</option>@endforeach</select>
@if($document->status === 'Disetujui')<label class="form-label" for="shipped">Tanggal barang dikirim ke supplier</label><input id="shipped" name="tanggal_dikirim" type="date" min="{{ $document->tanggal_retur }}" value="{{ now('Asia/Jakarta')->toDateString() }}" class="form-control mb-3" required>@endif
<button class="btn btn-primary">Simpan Status</button></form>@endif
@endcan
@if($document->status === 'Selesai' && $document->id_penerimaan)@can('access-route','penerimaan-barang.create')<a class="btn btn-outline-primary mt-3" href="{{ route('penerimaan-barang.create',['purchase_order'=>$document->penerimaanBarang->id_purchase_order,'retur'=>$document->getKey()]) }}">Catat Penerimaan Pengganti</a>@endcan @endif
</section><a class="btn btn-link" href="{{ route('retur-pembelian.index') }}">Kembali ke Retur Pembelian</a>
@endsection
