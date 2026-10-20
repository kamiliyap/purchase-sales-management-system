<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPurchaseOrder extends Model
{
    protected $table = 'detail_purchase_order';
    protected $primaryKey = 'id_detail_purchase_order';

    protected $fillable = [
        'id_purchase_order',
        'id_barang',
        'sku',
        'nama_barang',
        'kuantitas',
        'harga_unit',
        'diskon',
        'total_harga',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(
            PurchaseOrder::class,
            'id_purchase_order',
            'id_purchase_order'
        );
    }

    public function barang()
    {
        return $this->belongsTo(
            MBarang::class,
            'id_barang',
            'id_barang'
        );
    }
}