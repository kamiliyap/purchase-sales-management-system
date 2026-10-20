<?php

namespace App\Models;

use App\Casts\SalesMoney;
use Illuminate\Database\Eloquent\Model;

class DetailSalesOrder extends Model
{
    protected $table = 'detail_sales_order';

    protected $primaryKey = 'id_detail_sales_order';

    protected $casts = ['harga_jual' => SalesMoney::class, 'diskon' => SalesMoney::class, 'total_harga' => SalesMoney::class];

    protected $fillable = [
        'id_sales_order',
        'id_barang',
        'sku',
        'nama_barang',
        'kuantitas',
        'harga_jual',
        'diskon',
        'total_harga',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(
            SalesOrder::class,
            'id_sales_order',
            'id_sales_order'
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
