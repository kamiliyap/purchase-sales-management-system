@extends('layouts.app')
@section('title', 'Detail Invoice Pembelian')
@section('content')
    @include('pdf.buttons', ['pdfModule' => 'invoice-pembelian'])
    <a class="back-link" href="{{ route('invoice-pembelian.index') }}"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Invoice Pembelian</a>
    <section class="page-heading"><div><span class="eyebrow">PURCHASING</span><h1>{{ $document->no_invoice }}</h1><p>Detail invoice pembelian</p></div>@can('access-route', 'retur-pembelian.create')<a class="btn btn-primary" href="{{ route('retur-pembelian.create') }}">Buat Retur</a>@endcan</section>
    <section class="surface-card form-card">@include('purchasing.partials.detail-fields', ['fields' => ['no_invoice' => 'Nomor Invoice', 'purchaseOrder.no_order_pembelian' => 'Purchase Order', 'purchaseOrder.supplier.nama_supplier' => 'Supplier', 'tanggal_invoice' => 'Tanggal Invoice', 'jatuh_tempo' => 'Jatuh Tempo', 'total_invoice' => 'Total Invoice', 'status' => 'Status'], 'moneyFields' => ['total_invoice']])<a href="{{ route('purchase-order.show', $document->id_purchase_order) }}">Lihat Purchase Order <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
    <section class="surface-card form-card mt-4">
        <p>{{ $document->dibuat_otomatis ? 'Invoice dibuat otomatis oleh sistem setelah seluruh barang PO diterima dan diverifikasi Baik.' : 'Invoice historis; tidak diubah oleh proses otomatis.' }}</p>
        <h2 class="h6">Dokumen penerimaan terkait PO</h2>
        @forelse($document->purchaseOrder->penerimaanBarang as $receipt)<a class="d-block" href="{{ route('penerimaan-barang.show', $receipt) }}">{{ $receipt->no_penerimaan }} ? {{ $receipt->tanggal_penerimaan }}</a>@empty<p>Tidak ada dokumen penerimaan historis.</p>@endforelse
        @can('access-route', 'invoice-pembelian.update')
        <form method="POST" action="{{ route('invoice-pembelian.update', $document) }}" class="mt-3">@csrf @method('PATCH')
            <label for="invoice-status" class="form-label">Status invoice</label><select id="invoice-status" name="status" class="form-select">@foreach(\App\Support\PurchasingStatuses::INVOICE as $status)<option @selected($document->status === $status)>{{ $status }}</option>@endforeach</select><button class="btn btn-primary mt-2">Simpan Status</button>
        </form>@endcan
    </section>
    @if($document->returPembelian->isNotEmpty())<section class="surface-card form-card mt-4"><h2 class="h6">Retur terkait</h2>@foreach($document->returPembelian as $retur)<a class="d-block mt-2" href="{{ route('retur-pembelian.show', $retur->getKey()) }}">{{ $retur->no_retur }}</a>@endforeach</section>@endif
@endsection
