@if($orders->isEmpty())
    <div class="alert alert-info">Buat Sales Order terlebih dahulu atau pilih SO yang belum memiliki invoice. <a href="{{ route('sales-order.create') }}">Tambah Sales Order</a></div>
@else
    @php
        $fields = [
            'id_sales_order' => ['label' => 'Sales Order', 'type' => 'select', 'default' => $selectedOrder, 'options' => $orders->mapWithKeys(fn($order) => [$order->id_sales_order => $order->no_sales_order.' - '.$order->customer->nama_customer])],
            'no_invoice' => ['label' => 'Nomor Invoice', 'readonly' => true, 'default' => $documentNumber],
            'tanggal_invoice' => ['label' => 'Tanggal Invoice', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
            'jatuh_tempo' => ['label' => 'Jatuh Tempo', 'type' => 'date'],
            'total_invoice' => ['label' => 'Total Invoice (Rp)', 'readonly' => true, 'help' => 'Full invoice mengikuti total SO. Nilai final diambil dari SO di server.', 'default' => $orders->firstWhere('id_sales_order', $selectedOrder)?->total_harga ?? ''],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => \App\Support\SalesStatuses::options(\App\Support\SalesStatuses::INVOICE), 'default' => 'Belum Lunas'],
        ];
    @endphp
    @include('sales.partials.document-form', ['routeName' => 'invoice-penjualan', 'title' => 'Invoice Penjualan', 'icon' => 'receipt'])
    @push('scripts')
        <script type="application/json" id="sales-invoice-order-totals">{!! json_encode($orders->mapWithKeys(fn($order) => [$order->id_sales_order => $order->total_harga]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        <script src="{{ asset('assets/js/sales-documents.js') }}" defer></script>
    @endpush
@endif
