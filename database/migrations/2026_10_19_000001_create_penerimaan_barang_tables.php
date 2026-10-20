<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id('id_penerimaan');
            $table->string('no_penerimaan')->unique();
            $table->unsignedBigInteger('id_purchase_order');
            $table->date('tanggal_penerimaan');
            $table->foreignId('id_penerima')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign('id_purchase_order')->references('id_purchase_order')->on('purchase_order')->restrictOnDelete();
        });
        Schema::create('detail_penerimaan_barang', function (Blueprint $table) {
            $table->id('id_detail_penerimaan');
            $table->unsignedBigInteger('id_penerimaan');
            $table->unsignedBigInteger('id_barang');
            $table->unsignedInteger('kuantitas');
            $table->enum('kondisi', ['Baik', 'Rusak']);
            $table->string('keterangan', 1000)->nullable();
            $table->timestamps();
            $table->foreign('id_penerimaan')->references('id_penerimaan')->on('penerimaan_barang')->cascadeOnDelete();
            $table->foreign('id_barang')->references('id_barang')->on('m_barang')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_penerimaan_barang');
        Schema::dropIfExists('penerimaan_barang');
    }
};
