<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order', function (Blueprint $table) {
            $table->id('id_sales_order');

            $table->string('no_sales_order');
            $table->unsignedBigInteger('id_customer');

            $table->date('tanggal');
            $table->decimal('total_harga', 15, 2);

            $table->timestamps();

            $table->foreign('id_customer')
                ->references('id_customer')
                ->on('m_customer')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order');
    }
};