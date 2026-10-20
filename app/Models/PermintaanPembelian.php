<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

class PermintaanPembelian extends Model
{
    use HasDocumentNumber;

    protected $table = 'permintaan_pembelian';

    protected $primaryKey = 'id_permintaan';

    protected $fillable = [
        'no_faktur',
        'tanggal',
        'tanggal_diperlukan',
        'id_pemohon',
        'id_penyetuju',
        'status',
    ];

    public function details()
    {
        return $this->hasMany(DetailPermintaanPembelian::class, 'id_permintaan');
    }

    public function pemohon()
    {
        return $this->belongsTo(User::class, 'id_pemohon');
    }

    public function penyetuju()
    {
        return $this->belongsTo(User::class, 'id_penyetuju');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(
            PurchaseOrder::class,
            'id_permintaan',
            'id_permintaan'
        );
    }
}
