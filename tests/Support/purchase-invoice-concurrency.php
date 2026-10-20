<?php

use App\Models\InvoicePembelian;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\AutomaticPurchaseInvoiceService;
use App\Services\PenerimaanBarangService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Multi-process concurrency test on an isolated file database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = $argv[2] ?? '';
if (! preg_match('/^purchase_sales_invoice_test_[0-9a-f]{16}$/D', $name)) {
    throw new RuntimeException('Isolated test database required');
}
$mode = $argv[1] ?? '';
$file = storage_path('app/'.$name.'.sqlite');
if ($mode === 'cleanup') {
    DB::disconnect('sqlite');
    foreach ([$file, $file.'-wal', $file.'-shm'] as $artifact) {
        if (is_file($artifact)) {
            unlink($artifact);
        }
    }
    exit;
}
if ($mode === 'prepare') {
    if (is_file($file)) {
        throw new RuntimeException('Test database already exists');
    }
    touch($file);
}
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file,
    'database.connections.sqlite.busy_timeout' => 10000,
    'database.connections.sqlite.journal_mode' => 'WAL',
    'database.connections.sqlite.transaction_mode' => 'IMMEDIATE']);
DB::purge('sqlite');
if ($mode === 'prepare') {
    Artisan::call('migrate', ['--force' => true]);
    $user = User::factory()->create(['role' => 'Purchasing']);
    $pr = PermintaanPembelian::create(['tanggal' => '2026-10-13', 'tanggal_diperlukan' => '2026-10-15', 'id_pemohon' => $user->id, 'id_penyetuju' => $user->id, 'status' => 'Disetujui']);
    $supplier = MSupplier::create(['nama_supplier' => 'Test', 'alamat' => 'Test', 'telepon' => '123', 'email' => 'test@example.com']);
    $barang = MBarang::create(['sku' => 'TEST', 'nama_barang' => 'Test', 'satuan' => 'pcs', 'harga_beli' => 50, 'stok' => 9]);
    $order = PurchaseOrder::create(['id_permintaan' => $pr->getKey(), 'id_supplier' => $supplier->getKey(), 'tanggal' => '2026-10-13', 'tanggal_dibutuhkan' => '2026-10-15', 'jumlah' => 2, 'ppn' => 0, 'total_harga' => 100]);
    $order->details()->create(['id_barang' => $barang->getKey(), 'sku' => 'TEST', 'nama_barang' => 'Test', 'kuantitas' => 2, 'harga_unit' => 50, 'diskon' => 0, 'total_harga' => 100]);
    echo 'prepared';
    exit;
}
if ($mode === 'worker') {
    // Overlap the transaction windows. SQLite WAL permits concurrent readers;
    // a stale writer must roll back and retry before persisting its receipt.
    DB::transaction(function () {
        $order = PurchaseOrder::lockForUpdate()->firstOrFail();
        usleep(250000);
        app(PenerimaanBarangService::class)->save(['id_purchase_order' => $order->getKey(), 'tanggal_penerimaan' => '2026-10-14',
            'items' => [['id_barang' => MBarang::firstOrFail()->getKey(), 'kuantitas' => 1, 'kondisi' => 'Baik']]], User::firstOrFail()->id);
        app(AutomaticPurchaseInvoiceService::class)->createIfComplete($order->getKey());
    }, 5);
    echo 'saved';
    exit;
}
if ($mode === 'check') {
    $invoice = InvoicePembelian::firstOrFail();
    if (PenerimaanBarang::count() !== 2 || InvoicePembelian::count() !== 1 || ! $invoice->dibuat_otomatis || $invoice->total_invoice != 100 || PurchaseOrder::firstOrFail()->status !== 'Diinvoice' || MBarang::firstOrFail()->stok !== 9) {
        throw new RuntimeException('Concurrency invariants failed');
    }
    echo json_encode(['receipts' => 2, 'invoices' => 1, 'status' => 'Diinvoice', 'number' => $invoice->no_invoice]);
    exit;
}
throw new RuntimeException('Unknown mode');
