@extends('pdf.purchasing.layout')
@section('metadata')
<table class="meta"><tr><td><div class="label">Tanggal Purchase Order</div><div class="value">{{ $document->tanggal }}</div></td><td><div class="label">Tanggal Dibutuhkan</div><div class="value">{{ $document->tanggal_dibutuhkan }}</div></td></tr><tr><td><div class="label">Supplier / Vendor</div><div class="value">{{ $document->supplier?->nama_supplier ?? 'Supplier historis tidak tersedia' }}</div><div>{{ $document->supplier?->alamat }}</div></td><td><div class="label">Kontak Supplier</div>@if($document->supplier?->telepon)<div>Telepon: {{ $document->supplier->telepon }}</div>@endif @if($document->supplier?->email)<div>{{ $document->supplier->email }}</div>@endif</td></tr></table>
@endsection
@section('body')
@include('pdf.purchasing.order-lines', ['details' => $document->details, 'priceField' => 'harga_unit'])
@include('pdf.totals', ['details' => $document->details, 'tax' => $document->ppn, 'total' => $document->total_harga, 'totalLabel' => 'Total Purchase Order'])
@include('pdf.purchasing.signatures', ['signatures' => ['Penerima' => null, 'Perusahaan' => null]])
@endsection
