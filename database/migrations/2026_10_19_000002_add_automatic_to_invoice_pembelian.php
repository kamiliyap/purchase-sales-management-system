<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_pembelian', fn (Blueprint $table) => $table->boolean('dibuat_otomatis')->default(false));
    }

    public function down(): void
    {
        Schema::table('invoice_pembelian', fn (Blueprint $table) => $table->dropColumn('dibuat_otomatis'));
    }
};
