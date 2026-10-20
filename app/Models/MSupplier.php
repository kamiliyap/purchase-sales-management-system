<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MSupplier extends Model
{
    protected $table = 'm_supplier';
    protected $primaryKey = 'id_supplier';

    protected $fillable = [
        'nama_supplier',
        'alamat',
        'telepon',
        'email',
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(
            PurchaseOrder::class,
            'id_supplier',
            'id_supplier'
        );
    }
}