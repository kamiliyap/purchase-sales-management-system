<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_sales_order', function (Blueprint $table) {
            $table->id('id_detail_sales_order');

            $table->unsignedBigInteger('id_sales_order');
            $table->unsignedBigInteger('id_barang');

            $table->string('sku');
            $table->string('nama_barang');

            $table->integer('kuantitas');

            $table->decimal('harga_jual', 15, 2);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total_harga', 15, 2);

            $table->timestamps();

            $table->foreign('id_sales_order')
                ->references('id_sales_order')
                ->on('sales_order')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreign('id_barang')
                ->references('id_barang')
                ->on('m_barang')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_sales_order');
    }
};