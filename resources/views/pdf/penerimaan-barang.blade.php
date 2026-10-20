@extends('pdf.purchasing.layout')
@section('metadata')
<table class="meta"><tr><td><div class="label">Nomor Penerimaan</div><div class="value">{{ $document->no_penerimaan }}</div></td><td><div class="label">Nomor Order Pembelian (PO)</div><div class="value">{{ $document->purchaseOrder->no_order_pembelian }}</div></td></tr><tr><td><div class="label">Tanggal Penerimaan</div><div class="value">{{ $document->tanggal_penerimaan }}</div></td><td><div class="label">Penerima / Pemeriksa</div><div class="value">{{ $document->penerima?->name ?? 'Tidak tersedia pada data historis' }}</div></td></tr></table>

@endsection
@section('body')
<table class="items">
<colgroup><col style="width:14%"><col style="width:25%"><col style="width:11%"><col style="width:9%"><col style="width:9%"><col style="width:32%"></colgroup>
<thead><tr><th rowspan="2" style="width:14%">SKU</th><th rowspan="2" style="width:25%">Nama Barang</th><th rowspan="2" style="width:11%">Kuantitas</th><th colspan="2" style="width:18%">Kondisi Barang</th><th rowspan="2" style="width:32%">Keterangan</th></tr><tr><th style="width:9%">Baik</th><th style="width:9%">Rusak</th></tr></thead>
<tbody>@forelse($receiptRows as $row)
<tr><td>{{ $row['sku'] ?? 'Tidak tersedia pada PO' }}</td><td>{{ $row['nama_barang'] ?? 'Tidak tersedia pada PO' }}</td><td class="qty">{{ $row['kuantitas'] }}</td><td class="qty">{{ $row['baik'] }}</td><td class="qty">{{ $row['rusak'] }}</td><td class="remark">{{ $row['keterangan'] }}</td></tr>
@empty<tr><td colspan="6">Detail barang belum tercatat.</td></tr>@endforelse</tbody></table>
<table class="after-table"><tr><td class="verification"><div class="verification-box">Rincian kuantitas dan kondisi mengikuti dokumen penerimaan yang tercatat. Kolom tanda tangan disediakan untuk pengesahan penerima.</div></td><td class="signature"><div class="signature-title">Penerima / Pemeriksa Barang</div><div class="signature-space"></div><div class="signature-name">{{ $document->penerima?->name ?? 'Nama penerima tidak tersedia pada data historis' }}</div><div class="signature-date">Tanggal Penerimaan: {{ $document->tanggal_penerimaan }}</div></td></tr></table>

@endsection
