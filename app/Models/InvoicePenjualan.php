<?php

namespace App\Models;

use App\Casts\SalesMoney;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

class InvoicePenjualan extends Model
{
    use HasDocumentNumber;

    protected $table = 'invoice_penjualan';

    protected $primaryKey = 'id_invoice_penjualan';

    protected $casts = ['total_invoice' => SalesMoney::class];

    protected $fillable = [
        'id_sales_order',
        'no_invoice',
        'tanggal_invoice',
        'jatuh_tempo',
        'total_invoice',
        'status',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(
            SalesOrder::class,
            'id_sales_order',
            'id_sales_order'
        );
    }

    public function returPenjualan()
    {
        return $this->hasMany(
            ReturPenjualan::class,
            'id_invoice_penjualan',
            'id_invoice_penjualan'
        );
    }
}
