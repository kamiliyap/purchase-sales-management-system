<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPermintaanPembelian extends Model
{
    protected $table = 'detail_permintaan_pembelian';

    protected $primaryKey = 'id_detail_permintaan';

    protected $fillable = ['id_barang', 'sku', 'nama_barang', 'kuantitas', 'tanggal_dibutuhkan', 'keterangan'];

    public function permintaanPembelian()
    {
        return $this->belongsTo(PermintaanPembelian::class, 'id_permintaan');
    }

    public function barang()
    {
        return $this->belongsTo(MBarang::class, 'id_barang');
    }
}
