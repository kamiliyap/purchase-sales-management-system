<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_permintaan_pembelian', function (Blueprint $table) {
            $table->id('id_detail_permintaan');
            $table->unsignedBigInteger('id_permintaan');
            $table->unsignedBigInteger('id_barang');
            $table->string('sku');
            $table->string('nama_barang');
            $table->unsignedInteger('kuantitas');
            $table->date('tanggal_dibutuhkan');
            $table->string('keterangan', 1000)->nullable();
            $table->timestamps();
            $table->foreign('id_permintaan')->references('id_permintaan')->on('permintaan_pembelian')->cascadeOnDelete();
            $table->foreign('id_barang')->references('id_barang')->on('m_barang')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_permintaan_pembelian');
    }
};
