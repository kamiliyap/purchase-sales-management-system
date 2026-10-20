<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('invoice_penjualan')->select('id_sales_order')
            ->groupBy('id_sales_order')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Terdapat SO dengan beberapa invoice penjualan. Tinjau data lama sebelum menerapkan aturan full invoice; tidak ada invoice yang dihapus otomatis.');
        }
        Schema::table('invoice_penjualan', function (Blueprint $table) {
            $table->unique('id_sales_order', 'invoice_penjualan_so_unique');
        });
    }

    public function down(): void
    {
        // MySQL may discard its implicit FK index after adding the unique index.
        if (DB::getDriverName() === 'mysql'
            && ! Schema::hasIndex('invoice_penjualan', 'invoice_penjualan_id_sales_order_foreign')) {
            Schema::table('invoice_penjualan', function (Blueprint $table) {
                $table->index('id_sales_order', 'invoice_penjualan_id_sales_order_foreign');
            });
        }
        Schema::table('invoice_penjualan', function (Blueprint $table) {
            $table->dropUnique('invoice_penjualan_so_unique');
        });
    }
};
