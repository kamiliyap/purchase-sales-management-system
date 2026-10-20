<a href="{{ route('sales-order.index') }}" class="back-link">Kembali ke Sales Order</a>
<section class="page-heading"><div><span class="eyebrow">SALES</span><h1>{{ isset($document) ? 'Edit' : 'Tambah' }} Sales Order</h1><p>Susun order penjualan beserta rincian barangnya.</p></div></section>
@if($customers->isEmpty() || $barangs->isEmpty())
    <div class="alert alert-info">Lengkapi data berikut sebelum membuat SO:
        @if($customers->isEmpty())<a class="d-block" href="{{ route('customer.create') }}">Tambah Customer</a>@endif
        @if($barangs->isEmpty())<a class="d-block" href="{{ route('barang.create') }}">Tambah Barang</a>@endif
    </div>
@else
    @php
        $fields = [
            'id_customer' => ['label' => 'Customer', 'type' => 'select', 'options' => $customers->pluck('nama_customer', 'id_customer')],
            'no_sales_order' => ['label' => 'Nomor SO', 'readonly' => true, 'default' => $documentNumber],
            'tanggal' => ['label' => 'Tanggal', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
        ];
        $initialItems = isset($document) ? $document->details->map(fn($line) => $line->only(['id_barang', 'kuantitas', 'harga_jual', 'diskon']))->toArray() : [];
        $rawItems = old('items', $initialItems);
        $lineItems = is_array($rawItems) ? collect($rawItems)->filter(fn($item) => is_array($item))->take(100)->all() : [];
        if (empty($lineItems)) $lineItems = [['id_barang' => '', 'kuantitas' => 1, 'harga_jual' => '', 'diskon' => 0]];
    @endphp
    <form id="sales-order-form" method="POST" action="{{ isset($document) ? route('sales-order.update', $document->getKey()) : route('sales-order.store') }}">
        @csrf
        @isset($document) @method('PUT') @endisset
        <section class="surface-card form-card mb-4"><div class="card-heading"><h2>Informasi Sales Order</h2></div>@include('sales.partials.fields')</section>
        <section class="surface-card supplier-card mb-4">
            <div class="list-toolbar"><div><h2>Item Barang</h2><p class="text-secondary small mb-0 mt-1">Diskon berupa nominal rupiah per baris. Setiap barang hanya boleh dipilih sekali. Harga jual diisi untuk transaksi ini.</p></div><button type="button" class="btn btn-outline-primary" id="add-so-item">Tambah Item</button></div>
            <div class="table-responsive"><table class="table supplier-table po-items-table align-middle mb-0">
                <thead><tr><th>BARANG</th><th>SKU / NAMA</th><th>KUANTITAS</th><th>HARGA JUAL (Rp)</th><th>DISKON (Rp)</th><th>TOTAL (Rp)</th><th>AKSI</th></tr></thead>
                <tbody id="so-items">@foreach($lineItems as $index => $item)
                    @include('sales-order.partials.item-row')
                @endforeach</tbody>
            </table></div>
        </section>
        <section class="surface-card form-card">
            <dl class="po-summary mb-0"><div class="po-grand-total"><dt>Total Harga</dt><dd id="so-grand-total" role="status" aria-live="polite">Rp 0</dd></div></dl>
            <p id="so-calculation-error" class="text-danger small mt-3" role="alert" hidden></p>
            <div class="form-actions"><a class="btn btn-outline-secondary" href="{{ route('sales-order.index') }}">Batal</a><button type="submit" class="btn btn-primary">Simpan Sales Order</button></div>
        </section>
    </form>
    <template id="so-item-template">@include('sales-order.partials.item-row', ['index' => '__INDEX__', 'item' => ['id_barang' => '', 'kuantitas' => 1, 'harga_jual' => '', 'diskon' => 0]])</template>
    @push('scripts')<script src="{{ asset('assets/js/sales-order.js') }}" defer></script>@endpush
@endif
