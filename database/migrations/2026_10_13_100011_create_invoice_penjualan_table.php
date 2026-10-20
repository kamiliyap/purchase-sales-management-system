<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_penjualan', function (Blueprint $table) {
            $table->id('id_invoice_penjualan');

            $table->unsignedBigInteger('id_sales_order');

            $table->string('no_invoice');
            $table->date('tanggal_invoice');
            $table->date('jatuh_tempo');

            $table->decimal('total_invoice', 15, 2);
            $table->string('status');

            $table->timestamps();

            $table->foreign('id_sales_order')
                ->references('id_sales_order')
                ->on('sales_order')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_penjualan');
    }
};