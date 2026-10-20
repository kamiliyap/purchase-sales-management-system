<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_pembelian', function (Blueprint $table) {
            $table->id('id_permintaan');
            $table->string('no_faktur');
            $table->date('tanggal');
            $table->date('tanggal_diperlukan');
            $table->unsignedBigInteger('id_pemohon');
            $table->unsignedBigInteger('id_penyetuju');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_pembelian');
    }
};
