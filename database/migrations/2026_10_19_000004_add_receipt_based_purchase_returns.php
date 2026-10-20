<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retur_pembelian', function (Blueprint $table) {
            $table->unsignedBigInteger('id_invoice_pembelian')->nullable()->change();
            $table->unsignedBigInteger('id_penerimaan')->nullable();
            $table->foreign('id_penerimaan')->references('id_penerimaan')->on('penerimaan_barang')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('tanggal_dikirim')->nullable();
            foreach (['subtotal', 'diskon', 'ppn', 'total_retur'] as $column) {
                $table->decimal($column, 15, 2)->nullable();
            }
        });
        Schema::create('detail_retur_pembelian', function (Blueprint $table) {
            $table->id('id_detail_retur');
            $table->unsignedBigInteger('id_retur_pembelian');
            $table->unsignedBigInteger('id_detail_penerimaan');
            $table->unsignedBigInteger('id_detail_purchase_order');
            $table->string('sku');
            $table->string('nama_barang');
            $table->unsignedInteger('kuantitas');
            foreach (['harga_unit', 'diskon', 'subtotal', 'ppn', 'total_harga'] as $column) {
                $table->decimal($column, 15, 2);
            }
            $table->string('alasan', 1000);
            $table->timestamps();
            $table->foreign('id_retur_pembelian')->references('id_retur_pembelian')->on('retur_pembelian')->restrictOnDelete();
            $table->foreign('id_detail_penerimaan')->references('id_detail_penerimaan')->on('detail_penerimaan_barang')->restrictOnDelete();
            $table->foreign('id_detail_purchase_order')->references('id_detail_purchase_order')->on('detail_purchase_order')->restrictOnDelete();
        });
        Schema::table('detail_penerimaan_barang', function (Blueprint $table) {
            $table->unsignedBigInteger('id_detail_retur_asal')->nullable();
            $table->foreign('id_detail_retur_asal')->references('id_detail_retur')->on('detail_retur_pembelian')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('retur_pembelian')->whereNull('id_invoice_pembelian')->orWhereNotNull('id_penerimaan')->exists()
            || DB::table('detail_retur_pembelian')->exists()
            || DB::table('detail_penerimaan_barang')->whereNotNull('id_detail_retur_asal')->exists()) {
            throw new RuntimeException('Rollback ditolak: ada retur berbasis penerimaan/pengganti atau retur tanpa invoice. Data harus ditinjau tanpa menghapus histori otomatis.');
        }
        Schema::table('detail_penerimaan_barang', function (Blueprint $table) {
            $table->dropForeign(['id_detail_retur_asal']);
            $table->dropColumn('id_detail_retur_asal');
        });
        Schema::dropIfExists('detail_retur_pembelian');
        Schema::table('retur_pembelian', function (Blueprint $table) {
            foreach (['id_penerimaan', 'created_by', 'approved_by', 'shipped_by'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropColumn(['id_penerimaan', 'created_by', 'approved_by', 'shipped_by', 'tanggal_dikirim', 'subtotal', 'diskon', 'ppn', 'total_retur']);
            $table->unsignedBigInteger('id_invoice_pembelian')->nullable(false)->change();
        });
    }
};
