<?php

use App\Models\InvoicePembelian;
use App\Models\MBarang;
use App\Models\MSupplier;
use App\Models\PenerimaanBarang;
use App\Models\PermintaanPembelian;
use App\Models\PurchaseOrder;
use App\Models\ReturPembelian;
use App\Models\User;
use App\Services\PenerimaanBarangService;
use App\Services\ReturPembelianService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Multi-process concurrency test on an isolated file database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = $argv[2] ?? '';
if (! preg_match('/^purchase_sales_returns_test_[0-9a-f]{16}$/D', $name)) {
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
    $order->details()->update(['kuantitas' => 10, 'total_harga' => 500]);
    $order->update(['jumlah' => 10, 'total_harga' => 500]);
    app(PenerimaanBarangService::class)->save(['id_purchase_order' => $order->getKey(), 'tanggal_penerimaan' => '2026-10-14', 'items' => [
        ['id_barang' => $barang->getKey(), 'kuantitas' => 8, 'kondisi' => 'Baik'], ['id_barang' => $barang->getKey(), 'kuantitas' => 2, 'kondisi' => 'Rusak']]], $user->id);
    echo 'prepared';
    exit;
}
if ($mode === 'return-worker') {
    try {
        DB::transaction(function () {
            $order = PurchaseOrder::lockForUpdate()->firstOrFail();
            usleep(200000);
            $receipt = PenerimaanBarang::firstOrFail();
            app(ReturPembelianService::class)->create(['id_penerimaan' => $receipt->getKey(), 'tanggal_retur' => '2026-10-15', 'alasan' => 'Concurrency', 'items' => [
                ['id_detail_penerimaan' => $receipt->details()->where('kondisi', 'Rusak')->first()->getKey(), 'id_detail_purchase_order' => $order->details()->first()->getKey(), 'kuantitas' => 2, 'alasan' => 'Rusak']]], User::first()->id);
        }, 5);
        echo 'saved';
    } catch (ValidationException $exception) {
        echo 'rejected';
    }
    exit;
}
if ($mode === 'ship') {
    if (ReturPembelian::count() !== 1 || ReturPembelian::first()->details()->sum('kuantitas') !== 2) {
        throw new RuntimeException('Return quota violation');
    }
    $return = ReturPembelian::firstOrFail();
    app(ReturPembelianService::class)->transition($return, ['status' => 'Disetujui'], User::first()->id);
    app(ReturPembelianService::class)->transition($return, ['status' => 'Selesai', 'tanggal_dikirim' => '2026-10-16'], User::first()->id);
    echo 'shipped';
    exit;
}
if ($mode === 'replacement-worker') {
    try {
        DB::transaction(function () {
            $order = PurchaseOrder::lockForUpdate()->firstOrFail();
            usleep(200000);
            $line = ReturPembelian::first()->details()->first();
            app(PenerimaanBarangService::class)->save(['id_purchase_order' => $order->getKey(), 'tanggal_penerimaan' => '2026-10-17', 'items' => [
                ['id_barang' => MBarang::first()->getKey(), 'kuantitas' => 1, 'kondisi' => 'Baik', 'id_detail_retur_asal' => $line->getKey()]]], User::first()->id);
        }, 5);
        echo 'saved';
    } catch (ValidationException $exception) {
        echo 'rejected';
    }
    exit;
}
if ($mode === 'check') {
    $return = ReturPembelian::firstOrFail();
    $line = $return->details()->first();
    $invoice = InvoicePembelian::firstOrFail();
    if (ReturPembelian::count() !== 1 || $line->pengganti()->sum('kuantitas') !== 2 || PenerimaanBarang::count() !== 3 || InvoicePembelian::count() !== 1 || $invoice->total_invoice != 500 || PurchaseOrder::first()->status !== 'Diinvoice' || MBarang::first()->stok !== 9) {
        throw new RuntimeException('Replacement/invoice invariants failed');
    }
    echo json_encode(['returns' => 1, 'replacements' => 2, 'invoices' => 1, 'number' => $invoice->no_invoice]);
    exit;
}
throw new RuntimeException('Unknown mode');
