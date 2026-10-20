<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPenerimaanBarang extends Model
{
    protected $table = 'detail_penerimaan_barang';

    protected $primaryKey = 'id_detail_penerimaan';

    protected $fillable = ['id_barang', 'kuantitas', 'kondisi', 'keterangan', 'id_detail_retur_asal'];

    public function penerimaanBarang()
    {
        return $this->belongsTo(PenerimaanBarang::class, 'id_penerimaan');
    }

    public function returAsal()
    {
        return $this->belongsTo(DetailReturPembelian::class, 'id_detail_retur_asal');
    }

    public function detailRetur()
    {
        return $this->hasMany(DetailReturPembelian::class, 'id_detail_penerimaan');
    }

    public function barang()
    {
        return $this->belongsTo(MBarang::class, 'id_barang');
    }
}
