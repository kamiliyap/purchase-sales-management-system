<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoicePembelianController;
use App\Http\Controllers\InvoicePenjualanController;
use App\Http\Controllers\PenerimaanBarangController;
use App\Http\Controllers\PermintaanPembelianController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReturPembelianController;
use App\Http\Controllers\ReturPenjualanController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'module.permission'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    foreach (['permintaan-pembelian', 'purchase-order', 'penerimaan-barang', 'invoice-pembelian', 'invoice-penjualan', 'retur-pembelian'] as $module) {
        $parameter = str_replace('-', '_', $module);
        Route::get($module.'/{'.$parameter.'}/pdf/preview', TransactionPdfController::class)->whereNumber($parameter)->defaults('type', $module)->defaults('download', false)->name($module.'.preview-pdf');
        Route::get($module.'/{'.$parameter.'}/pdf/download', TransactionPdfController::class)->whereNumber($parameter)->defaults('type', $module)->defaults('download', true)->name($module.'.download-pdf');
    }

    Route::resource('supplier', SupplierController::class)->except('show');
    Route::resource('customer', CustomerController::class);
    Route::resource('barang', BarangController::class);
    Route::resource('permintaan-pembelian', PermintaanPembelianController::class)->parameters(['permintaan-pembelian' => 'permintaan_pembelian']);
    Route::resource('purchase-order', PurchaseOrderController::class)->parameters(['purchase-order' => 'purchase_order']);
    Route::resource('penerimaan-barang', PenerimaanBarangController::class)->parameters(['penerimaan-barang' => 'penerimaan_barang']);
    Route::resource('invoice-pembelian', InvoicePembelianController::class)->parameters(['invoice-pembelian' => 'invoice_pembelian'])->only(['index', 'create', 'store', 'show', 'update']);
    Route::resource('retur-pembelian', ReturPembelianController::class)->parameters(['retur-pembelian' => 'retur_pembelian'])->only(['index', 'create', 'store', 'show', 'update']);
    Route::resource('sales-order', SalesOrderController::class)->parameters(['sales-order' => 'sales_order']);
    Route::resource('invoice-penjualan', InvoicePenjualanController::class)->parameters(['invoice-penjualan' => 'invoice_penjualan'])->only(['index', 'create', 'store', 'show']);
    Route::resource('retur-penjualan', ReturPenjualanController::class)->parameters(['retur-penjualan' => 'retur_penjualan'])->only(['index', 'create', 'store', 'show']);
});
