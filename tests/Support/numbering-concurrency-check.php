<?php

// Standalone multi-process check, restricted to a disposable database name.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'mysql-lock') {
    $document = new App\Models\PermintaanPembelian;
    $interval = [];
    app(App\Services\DocumentNumberService::class)->save($document, function () use (&$interval) {
        $interval['entered'] = microtime(true);
        usleep(300000);
        $interval['left'] = microtime(true);

        return true;
    });
    // No insert is performed; this checks the real MySQL lock without changing data.
    echo json_encode($interval).PHP_EOL;
    exit;
}

$database = getenv('NUMBER_TEST_DATABASE');
if (! is_string($database) || ! preg_match('/^purchase_sales_numbering_test_[0-9]+$/D', $database)) {
    throw new RuntimeException('An isolated numbering test database is required.');
}
$mode = $argv[1] ?? '';
$db = Illuminate\Support\Facades\DB::class;
$driver = getenv('NUMBER_TEST_DRIVER') ?: 'mysql';
if (! in_array($driver, ['mysql', 'sqlite'], true)) {
    throw new RuntimeException('Unsupported test driver.');
}
$sqliteFile = storage_path('app/'.$database.'.sqlite');
if ($mode === 'prepare' && $driver === 'mysql') {
    $db::connection('mysql')->statement('CREATE DATABASE `'.$database.'`');
}
if ($mode === 'cleanup') {
    if ($driver === 'mysql') {
        $db::connection('mysql')->statement('DROP DATABASE `'.$database.'`');
    } elseif (is_file($sqliteFile)) {
        unlink($sqliteFile);
    }
    echo "Temporary numbering test database removed.\n";
    exit;
}
if ($driver === 'sqlite') {
    if ($mode === 'prepare') {
        if (is_file($sqliteFile)) {
            throw new RuntimeException('Test database already exists.');
        }
        touch($sqliteFile);
    }
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $sqliteFile]);
} else {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database]);
}
$db::purge($driver);
if ($mode === 'prepare') {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    try {
        $db::transaction(function () {
            App\Models\PermintaanPembelian::create(['tanggal' => '2026-10-14', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => 1, 'id_penyetuju' => 2, 'status' => 'Draft']);
            throw new RuntimeException('Expected numbering rollback');
        });
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Expected numbering rollback') {
            throw $exception;
        }
    }
    if (App\Models\PermintaanPembelian::count() !== 0) {
        throw new RuntimeException('Failed transaction was not rolled back.');
    }
    App\Models\MSupplier::create(['nama_supplier' => 'Supplier Concurrency', 'alamat' => 'Jakarta', 'telepon' => '081234567890', 'email' => 'supplier@example.com']);
    App\Models\MCustomer::create(['nama_customer' => 'Customer Concurrency', 'alamat' => 'Jakarta', 'telepon' => '081234567890', 'email' => 'customer@example.com']);
    echo "Isolated $driver database ready; rollback checked.\n";
    exit;
}

$documents = [
    'PR' => [App\Models\PermintaanPembelian::class, 'no_faktur'],
    'PO' => [App\Models\PurchaseOrder::class, 'no_order_pembelian'],
    'SO' => [App\Models\SalesOrder::class, 'no_sales_order'],
    'INV-B' => [App\Models\InvoicePembelian::class, 'no_invoice'],
    'INV-J' => [App\Models\InvoicePenjualan::class, 'no_invoice'],
    'RET-B' => [App\Models\ReturPembelian::class, 'no_retur'],
    'RET-J' => [App\Models\ReturPenjualan::class, 'no_retur'],
];
if ($mode === 'worker') {
    $supplier = App\Models\MSupplier::firstOrFail();
    $customer = App\Models\MCustomer::firstOrFail();
    for ($i = 0; $i < 3; $i++) {
        $request = App\Models\PermintaanPembelian::create(['tanggal' => '2026-10-14', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => 1, 'id_penyetuju' => 2, 'status' => 'Draft']);
        $order = App\Models\PurchaseOrder::create(['id_permintaan' => $request->getKey(), 'id_supplier' => $supplier->getKey(), 'tanggal' => '2026-10-14', 'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 1, 'ppn' => 0, 'total_harga' => 100]);
        $sales = App\Models\SalesOrder::create(['id_customer' => $customer->getKey(), 'tanggal' => '2026-10-14', 'total_harga' => 100]);
        $buyInvoice = App\Models\InvoicePembelian::create(['id_purchase_order' => $order->getKey(), 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-15', 'total_invoice' => 100, 'status' => 'Belum Lunas']);
        $saleInvoice = App\Models\InvoicePenjualan::create(['id_sales_order' => $sales->getKey(), 'tanggal_invoice' => '2026-10-14', 'jatuh_tempo' => '2026-10-15', 'total_invoice' => 100, 'status' => 'Belum Lunas']);
        App\Models\ReturPembelian::create(['id_invoice_pembelian' => $buyInvoice->getKey(), 'tanggal_retur' => '2026-10-15', 'alasan' => 'Rusak', 'status' => 'Diajukan']);
        App\Models\ReturPenjualan::create(['id_invoice_penjualan' => $saleInvoice->getKey(), 'tanggal_retur' => '2026-10-15', 'alasan' => 'Rusak', 'status' => 'Diajukan']);
    }
    echo "Worker completed: 21 documents.\n";
    exit;
}
if ($mode === 'check') {
    $year = now('Asia/Jakarta')->year;
    foreach ($documents as $prefix => [$class, $column]) {
        $numbers = $class::pluck($column)->all();
        $expected = array_map(fn ($i) => "$prefix/$year/".str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 15));
        sort($numbers);
        if ($numbers !== $expected) {
            throw new RuntimeException('Missing or duplicate numbers for '.$prefix);
        }
        echo "$prefix: 15 unique numbers, 0001 through 0015.\n";
    }
    exit;
}
throw new RuntimeException('Unknown test mode.');
