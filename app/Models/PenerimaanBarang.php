<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanBarang extends Model
{
    use Concerns\HasDocumentNumber;

    protected $table = 'penerimaan_barang';

    protected $primaryKey = 'id_penerimaan';

    protected $fillable = ['id_purchase_order', 'tanggal_penerimaan', 'id_penerima'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'id_purchase_order');
    }

    public function details()
    {
        return $this->hasMany(DetailPenerimaanBarang::class, 'id_penerimaan');
    }

    public function returPembelian()
    {
        return $this->hasMany(ReturPembelian::class, 'id_penerimaan');
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'id_penerima');
    }
}
