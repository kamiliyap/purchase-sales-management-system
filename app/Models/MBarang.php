<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MBarang extends Model
{
    protected $table = 'm_barang';

    protected $primaryKey = 'id_barang';

    protected $fillable = [
        'sku',
        'nama_barang',
        'satuan',
        'harga_beli',
        'stok',
    ];

    public function detailPermintaanPembelian()
    {
        return $this->hasMany(DetailPermintaanPembelian::class, 'id_barang');
    }

    public function detailPurchaseOrders()
    {
        return $this->hasMany(
            DetailPurchaseOrder::class,
            'id_barang',
            'id_barang'
        );
    }

    public function detailSalesOrders()
    {
        return $this->hasMany(
            DetailSalesOrder::class,
            'id_barang',
            'id_barang'
        );
    }
}
