<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailReturPembelian extends Model
{
    protected $table = 'detail_retur_pembelian';

    protected $primaryKey = 'id_detail_retur';

    protected $fillable = ['id_detail_penerimaan', 'id_detail_purchase_order', 'sku', 'nama_barang', 'kuantitas', 'harga_unit', 'diskon', 'subtotal', 'ppn', 'total_harga', 'alasan'];

    protected function casts(): array
    {
        return array_fill_keys(['harga_unit', 'diskon', 'subtotal', 'ppn', 'total_harga'], 'decimal:2');
    }

    public function returPembelian()
    {
        return $this->belongsTo(ReturPembelian::class, 'id_retur_pembelian');
    }

    public function detailPenerimaan()
    {
        return $this->belongsTo(DetailPenerimaanBarang::class, 'id_detail_penerimaan');
    }

    public function detailPurchaseOrder()
    {
        return $this->belongsTo(DetailPurchaseOrder::class, 'id_detail_purchase_order');
    }

    public function pengganti()
    {
        return $this->hasMany(DetailPenerimaanBarang::class, 'id_detail_retur_asal');
    }
}
