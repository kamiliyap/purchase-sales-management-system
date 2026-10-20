<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MCustomer extends Model
{
    protected $table = 'm_customer';
    protected $primaryKey = 'id_customer';

    protected $fillable = [
        'nama_customer',
        'alamat',
        'telepon',
        'email',
    ];

    public function salesOrders()
    {
        return $this->hasMany(
            SalesOrder::class,
            'id_customer',
            'id_customer'
        );
    }
}