<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order', function (Blueprint $table) {
            $table->id('id_purchase_order');

            $table->string('no_order_pembelian');

            $table->unsignedBigInteger('id_permintaan');
            $table->unsignedBigInteger('id_supplier');

            $table->date('tanggal');
            $table->date('tanggal_dibutuhkan');

            $table->integer('jumlah');

            $table->decimal('ppn', 15, 2)->default(0);
            $table->decimal('total_harga', 15, 2)->default(0);

            $table->timestamps();

            $table->foreign('id_permintaan')
                ->references('id_permintaan')
                ->on('permintaan_pembelian')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('id_supplier')
                ->references('id_supplier')
                ->on('m_supplier')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order');
    }
};