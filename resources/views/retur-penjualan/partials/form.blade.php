@if($invoices->isEmpty())
    <div class="alert alert-info">Buat invoice penjualan terlebih dahulu untuk mencatat retur. <a href="{{ route('invoice-penjualan.create') }}">Tambah Invoice Penjualan</a></div>
@else
    @php
        $fields = [
            'id_invoice_penjualan' => ['label' => 'Invoice Penjualan', 'type' => 'select', 'default' => $selectedInvoice, 'options' => $invoices->mapWithKeys(fn($invoice) => [$invoice->id_invoice_penjualan => $invoice->no_invoice.' - '.$invoice->salesOrder->no_sales_order])],
            'no_retur' => ['label' => 'Nomor Retur', 'readonly' => true, 'default' => $documentNumber],
            'tanggal_retur' => ['label' => 'Tanggal Retur', 'type' => 'date', 'default' => now('Asia/Jakarta')->toDateString()],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => \App\Support\SalesStatuses::options(\App\Support\SalesStatuses::RETURN), 'default' => 'Diajukan'],
            'alasan' => ['label' => 'Alasan Retur', 'type' => 'textarea', 'wide' => true],
        ];
    @endphp
    @include('sales.partials.document-form', ['routeName' => 'retur-penjualan', 'title' => 'Retur Penjualan', 'icon' => 'box-arrow-left'])
@endif
