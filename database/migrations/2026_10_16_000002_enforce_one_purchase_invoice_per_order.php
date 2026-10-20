<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('invoice_pembelian')->select('id_purchase_order')
            ->groupBy('id_purchase_order')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Terdapat PO dengan beberapa invoice pembelian. Tinjau data lama sebelum menerapkan aturan full invoice; tidak ada invoice yang dihapus otomatis.');
        }
        Schema::table('invoice_pembelian', function (Blueprint $table) {
            $table->unique('id_purchase_order', 'invoice_pembelian_po_unique');
        });
    }

    public function down(): void
    {
        // MySQL may discard its implicit FK index after adding the unique index.
        if (DB::getDriverName() === 'mysql'
            && ! Schema::hasIndex('invoice_pembelian', 'invoice_pembelian_id_purchase_order_foreign')) {
            Schema::table('invoice_pembelian', function (Blueprint $table) {
                $table->index('id_purchase_order', 'invoice_pembelian_id_purchase_order_foreign');
            });
        }
        Schema::table('invoice_pembelian', function (Blueprint $table) {
            $table->dropUnique('invoice_pembelian_po_unique');
        });
    }
};
