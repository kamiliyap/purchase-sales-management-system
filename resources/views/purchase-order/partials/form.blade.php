<a href="{{ route('purchase-order.index') }}" class="back-link"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke Purchase Order</a>
<section class="page-heading"><div><span class="eyebrow">PURCHASING</span><h1>{{ isset($document) ? 'Edit' : 'Tambah' }} Purchase Order</h1><p>Susun order pembelian beserta rincian barangnya.</p></div></section>
@if($requests->isEmpty() || $suppliers->isEmpty() || $barangs->isEmpty())
    <div class="alert alert-info">Lengkapi data berikut sebelum membuat PO:
        @if($requests->isEmpty())<a class="d-block" href="{{ route('permintaan-pembelian.create') }}">Tambah Permintaan Pembelian</a>@endif
        @if($suppliers->isEmpty())<a class="d-block" href="{{ route('supplier.create') }}">Tambah Supplier</a>@endif
        @if($barangs->isEmpty())<a class="d-block" href="{{ route('barang.create') }}">Tambah Barang</a>@endif
    </div>
@else
    @php
        $fields = [
            'id_permintaan' => ['default' => ($selectedRequest ?? null)?->getKey() ?? null, 'label' => 'Permintaan Pembelian', 'type' => 'select', 'options' => $requests->pluck('no_faktur', 'id_permintaan')],
            'id_supplier' => ['label' => 'Supplier', 'type' => 'select', 'options' => $suppliers->pluck('nama_supplier', 'id_supplier')],
            'no_order_pembelian' => ['label' => 'Nomor PO', 'wide' => true, 'readonly' => true, 'default' => $documentNumber],
            'tanggal' => ['label' => 'Tanggal', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
            'tanggal_dibutuhkan' => ['label' => 'Tanggal Dibutuhkan', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
        ];
        $lineItems = old('items', isset($document) ? $document->details->map(fn($line) => $line->only(['id_barang', 'kuantitas', 'harga_unit', 'diskon']))->toArray() : (($requestItems ?? null) ?: [['id_barang' => '', 'kuantitas' => 1, 'harga_unit' => '', 'diskon' => 0]]));
        $savedSubtotal = isset($document) ? $document->details->sum('total_harga') : 0;
        $savedRate = $savedSubtotal > 0 ? round($document->ppn / $savedSubtotal * 100, 2) : 0;
    @endphp
    <form id="purchase-order-form" method="POST" action="{{ isset($document) ? route('purchase-order.update', $document->getKey()) : route('purchase-order.store') }}">
        @csrf
        @isset($document) @method('PUT') @endisset
        <section class="surface-card form-card mb-4"><div class="card-heading"><div><h2>Informasi Purchase Order</h2><p>Kolom bertanda <span class="text-danger">*</span> wajib diisi.</p></div><span class="stat-icon blue"><i class="bi bi-bag-check" aria-hidden="true"></i></span></div>@include('purchasing.partials.fields')</section>
        <section class="surface-card supplier-card mb-4">
            <div class="list-toolbar"><div><h2>Item Barang</h2><p class="text-secondary small mb-0 mt-1">Diskon adalah nominal rupiah untuk satu baris, bukan persen.</p></div><button type="button" class="btn btn-outline-primary" id="add-po-item"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tambah Item</button></div>
            <div class="table-responsive"><table class="table supplier-table po-items-table align-middle mb-0"><thead><tr><th scope="col">BARANG</th><th scope="col">SKU / NAMA</th><th scope="col">KUANTITAS</th><th scope="col">HARGA UNIT (Rp)</th><th scope="col">DISKON (Rp)</th><th scope="col">TOTAL (Rp)</th><th scope="col">AKSI</th></tr></thead><tbody id="po-items">
                @foreach($lineItems as $index => $item)
                    @include('purchase-order.partials.item-row')
                @endforeach
            </tbody></table></div>
        </section>
        <section class="surface-card form-card">
            <div class="row g-4"><div class="col-lg-6"><label for="ppn_rate" class="form-label">PPN (%) <span class="text-danger">*</span></label><input class="form-control @error('ppn_rate') is-invalid @enderror" id="ppn_rate" name="ppn_rate" type="number" min="0" max="100" step="0.01" value="{{ old('ppn_rate', $savedRate) }}" required>@error('ppn_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror<p class="form-text">Isi persentase PPN yang berlaku untuk transaksi ini. Total dihitung setelah diskon.</p></div><div class="col-lg-6"><dl class="po-summary mb-0"><div><dt>Subtotal setelah diskon</dt><dd id="po-subtotal">Rp 0</dd></div><div><dt>PPN</dt><dd id="po-tax">Rp 0</dd></div><div class="po-grand-total"><dt>Total Harga</dt><dd id="po-grand-total" role="status" aria-live="polite">Rp 0</dd></div></dl></div></div>
            <p id="po-calculation-error" class="text-danger small mt-3" role="alert" hidden></p>
            <div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('purchase-order.index') }}">Batal</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Simpan Purchase Order</button></div>
        </section>
    </form>
    <template id="po-item-template">@include('purchase-order.partials.item-row', ['index' => '__INDEX__', 'item' => ['id_barang' => '', 'kuantitas' => 1, 'harga_unit' => '', 'diskon' => 0]])</template>
    @push('scripts')<script src="{{ asset('assets/js/purchase-order.js') }}" defer></script>@endpush
@endif
