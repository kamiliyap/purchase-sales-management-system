@if(!$receipt)<label class="form-label" for="return-preview">Nomor Retur (preview)</label><input id="return-preview" name="no_retur" value="{{ $documentNumber }}" readonly class="form-control mb-3">@endif
<section class="surface-card form-card mb-4"><form method="GET" action="{{ route('retur-pembelian.create') }}"><label for="receipt" class="form-label">Dokumen Penerimaan Barang Rusak</label><select id="receipt" name="penerimaan" class="form-select" required><option value="">Pilih Penerimaan</option>@foreach($receipts as $choice)<option value="{{ $choice->getKey() }}" @selected($receipt?->getKey() == $choice->getKey())>{{ $choice->no_penerimaan }} / {{ $choice->purchaseOrder->no_order_pembelian }}</option>@endforeach</select><button class="btn btn-outline-primary mt-3">Ambil Barang Rusak</button></form>@if($receipts->isEmpty())<p class="mt-3">Belum ada penerimaan barang Rusak. Retur baru wajib berdasarkan Penerimaan Barang.</p>@endif</section>
@if($receipt)
<section class="surface-card form-card"><h2 class="h5">{{ config('company.name') }}</h2><p>Penerimaan: {{ $receipt->no_penerimaan }} / {{ $receipt->tanggal_penerimaan }}<br>PO: {{ $receipt->purchaseOrder->no_order_pembelian }}<br>Supplier: {{ $receipt->purchaseOrder->supplier?->nama_supplier }}</p>
<form method="POST" action="{{ route('retur-pembelian.store') }}">@csrf
<input type="hidden" name="id_penerimaan" value="{{ $receipt->getKey() }}">
<label class="form-label" for="no_retur">Nomor Retur</label><input id="no_retur" name="no_retur" value="{{ $documentNumber }}" readonly class="form-control mb-3">
<label class="form-label" for="tanggal_retur">Tanggal Retur</label><input id="tanggal_retur" name="tanggal_retur" type="date" min="{{ $receipt->tanggal_penerimaan }}" value="{{ old('tanggal_retur', now('Asia/Jakarta')->toDateString()) }}" required class="form-control mb-3">
<label class="form-label" for="alasan">Alasan Retur</label><textarea id="alasan" name="alasan" maxlength="255" required class="form-control mb-3">{{ old('alasan') }}</textarea>
<p>Status awal: <strong>Diajukan</strong>. Hapus baris yang tidak diretur. Harga dan alokasi diskon/PPN ditetapkan server dari PO; nilai retur tidak mengurangi invoice.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Detail Penerimaan Rusak</th><th>Baris PO / Harga</th><th>Qty Retur</th><th>Alasan Barang</th><th>Aksi</th></tr></thead><tbody>
@php($index = 0)
@foreach($receipt->details->where('kondisi','Rusak') as $source)
@php($remaining = $source->kuantitas - $source->detailRetur->filter(fn($line) => $line->returPembelian->status !== 'Ditolak')->sum('kuantitas'))
@if($remaining > 0)
@php($priceLines = $receipt->purchaseOrder->details->where('id_barang',$source->id_barang)->when($source->id_detail_retur_asal, fn($lines) => $lines->where('id_detail_purchase_order',$source->returAsal?->id_detail_purchase_order)))
<tr><td>{{ $priceLines->first()?->sku }} / {{ $priceLines->first()?->nama_barang }}<br>Detail #{{ $source->getKey() }}; tersedia {{ $remaining }}<input type="hidden" name="items[{{ $index }}][id_detail_penerimaan]" value="{{ $source->getKey() }}"></td><td><select aria-label="Baris PO sumber harga" name="items[{{ $index }}][id_detail_purchase_order]" class="form-select" required><option value="">Pilih baris PO</option>@foreach($priceLines as $line)<option value="{{ $line->getKey() }}" @selected(old('items.'.$index.'.id_detail_purchase_order', $priceLines->count() === 1 ? $line->getKey() : null) == $line->getKey())>#{{ $line->getKey() }} / {{ $line->sku }} / {{ \App\Support\PdfAmounts::money($line->harga_unit) }}</option>@endforeach</select></td><td><input aria-label="Kuantitas retur" name="items[{{ $index }}][kuantitas]" type="number" min="1" max="{{ $remaining }}" value="{{ old('items.'.$index.'.kuantitas', $remaining) }}" class="form-control" required></td><td><input aria-label="Alasan barang" name="items[{{ $index }}][alasan]" maxlength="1000" value="{{ old('items.'.$index.'.alasan', $source->keterangan) }}" class="form-control" required></td><td><button type="button" data-remove-return class="btn btn-outline-danger">Hapus</button></td></tr>
@php($index++)
@endif @endforeach
</tbody></table></div>
@if($index)<button class="btn btn-primary">Ajukan Retur</button>@else<p>Seluruh barang Rusak telah direservasi/diretur.</p>@endif
</form></section>
@endif
@push('scripts')<script src="{{ asset('assets/js/purchase-return.js') }}" defer></script>@endpush
