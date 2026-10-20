@extends('pdf.purchasing.layout')
@section('metadata')
<table class="meta"><tr><td><div class="label">Tanggal Permintaan</div><div class="value">{{ $document->tanggal }}</div></td><td><div class="label">Tanggal Diperlukan</div><div class="value">{{ $document->tanggal_diperlukan }}</div></td></tr></table>
@endsection
@section('body')
<table class="items"><thead><tr><th style="width:14%">SKU</th><th style="width:25%">Nama Barang</th><th style="width:11%">Kuantitas</th><th style="width:18%">Dibutuhkan</th><th style="width:32%">Keterangan</th></tr></thead><tbody>
@forelse($document->details as $item)
@if(! $loop->last)
<tr><td>{{ $item->sku }}</td><td>{{ $item->nama_barang }}</td><td class="right">{{ $item->kuantitas }}</td><td>{{ $item->tanggal_dibutuhkan }}</td><td>{{ $item->keterangan }}</td></tr>
@else
<tr><td colspan="5" style="padding:0; border:0">
<table class="items" style="margin:0"><tr><td style="width:14%">{{ $item->sku }}</td><td style="width:25%">{{ $item->nama_barang }}</td><td class="right" style="width:11%">{{ $item->kuantitas }}</td><td style="width:18%">{{ $item->tanggal_dibutuhkan }}</td><td style="width:32%">{{ $item->keterangan }}</td></tr></table>
<div class="request-signoff">@include('pdf.purchasing.signatures', ['signatures' => ['Pemohon' => $document->pemohon?->name, 'Penyetuju (sesuai status dokumen)' => $document->penyetuju?->name]])</div>
</td></tr>
@endif
@empty<tr><td colspan="5">Detail barang belum tercatat pada dokumen historis.</td></tr>@endforelse</tbody></table>
@if($document->details->isEmpty())@include('pdf.purchasing.signatures', ['signatures' => ['Pemohon' => $document->pemohon?->name, 'Penyetuju (sesuai status dokumen)' => $document->penyetuju?->name]])@endif
@endsection
