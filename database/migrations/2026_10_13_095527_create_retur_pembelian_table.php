<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retur_pembelian', function (Blueprint $table) {
            $table->id('id_retur_pembelian');

            $table->unsignedBigInteger('id_invoice_pembelian');

            $table->string('no_retur');
            $table->date('tanggal_retur');

            $table->string('alasan');
            $table->string('status');

            $table->timestamps();

            $table->foreign('id_invoice_pembelian')
                ->references('id_invoice_pembelian')
                ->on('invoice_pembelian')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retur_pembelian');
    }
};