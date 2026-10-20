@extends('layouts.app')
@section('title', 'Penerimaan Barang')
@section('content')
<section class="page-heading"><div><h1>Penerimaan Barang</h1><p>Penerimaan barang berdasarkan Purchase Order.</p></div>@can('access-route', 'penerimaan-barang.create')<a class="btn btn-primary" href="{{ route('penerimaan-barang.create') }}">Tambah Penerimaan</a>@endcan</section>
<section class="surface-card form-card"><table class="table"><thead><tr><th>No Penerimaan</th><th>Tanggal</th><th>Purchase Order</th><th>Penerima</th></tr></thead><tbody>@forelse($records as $record)<tr><td><a href="{{ route('penerimaan-barang.show', $record) }}">{{ $record->no_penerimaan }}</a></td><td>{{ $record->tanggal_penerimaan }}</td><td>{{ $record->purchaseOrder->no_order_pembelian }}</td><td>{{ $record->penerima->name }}</td></tr>@empty<tr><td colspan="4">Belum ada Penerimaan Barang.</td></tr>@endforelse</tbody></table></section>
@endsection
