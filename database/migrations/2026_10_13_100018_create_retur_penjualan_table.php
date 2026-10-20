<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_penjualan', function (Blueprint $table) {
            $table->id('id_retur_penjualan');

            $table->unsignedBigInteger('id_invoice_penjualan');

            $table->string('no_retur');
            $table->date('tanggal_retur');

            $table->string('alasan');
            $table->string('status');

            $table->timestamps();

            $table->foreign('id_invoice_penjualan')
                ->references('id_invoice_penjualan')
                ->on('invoice_penjualan')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_penjualan');
    }
};