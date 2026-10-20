<?php

namespace App\Models;

use App\Casts\SalesMoney;
use App\Models\Concerns\HasDocumentNumber;
use App\Support\SalesStatuses;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasDocumentNumber;

    protected $table = 'sales_order';

    protected $primaryKey = 'id_sales_order';

    protected $casts = ['total_harga' => SalesMoney::class];

    protected $fillable = [
        'no_sales_order',
        'id_customer',
        'tanggal',
        'total_harga',
    ];

    public function getStatusAttribute(): string
    {
        $invoiced = array_key_exists('invoices_exists', $this->attributes)
            ? (bool) $this->attributes['invoices_exists']
            : ($this->relationLoaded('invoices') ? $this->invoices->isNotEmpty() : $this->invoices()->exists());

        return $invoiced ? SalesStatuses::ORDER_INVOICED : SalesStatuses::ORDER_OPEN;
    }

    public function invoice()
    {
        return $this->hasOne(InvoicePenjualan::class, 'id_sales_order', 'id_sales_order');
    }

    public function customer()
    {
        return $this->belongsTo(
            MCustomer::class,
            'id_customer',
            'id_customer'
        );
    }

    public function details()
    {
        return $this->hasMany(
            DetailSalesOrder::class,
            'id_sales_order',
            'id_sales_order'
        );
    }

    public function invoices()
    {
        return $this->hasMany(
            InvoicePenjualan::class,
            'id_sales_order',
            'id_sales_order'
        );
    }
}
