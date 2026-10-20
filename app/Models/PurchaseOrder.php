<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Support\PurchasingStatuses;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasDocumentNumber;

    protected $table = 'purchase_order';

    protected $primaryKey = 'id_purchase_order';

    protected $fillable = [
        'no_order_pembelian',
        'id_permintaan',
        'id_supplier',
        'tanggal',
        'tanggal_dibutuhkan',
        'jumlah',
        'ppn',
        'total_harga',
    ];

    // Status is derived from the invoice, never supplied by a form.
    public function getStatusAttribute(): string
    {
        $invoiced = array_key_exists('invoices_exists', $this->attributes)
            ? (bool) $this->attributes['invoices_exists']
            : ($this->relationLoaded('invoices') ? $this->invoices->isNotEmpty() : $this->invoices()->exists());

        return $invoiced ? PurchasingStatuses::ORDER_INVOICED : PurchasingStatuses::ORDER_OPEN;
    }

    public function permintaanPembelian()
    {
        return $this->belongsTo(
            PermintaanPembelian::class,
            'id_permintaan',
            'id_permintaan'
        );
    }

    public function supplier()
    {
        return $this->belongsTo(
            MSupplier::class,
            'id_supplier',
            'id_supplier'
        );
    }

    public function details()
    {
        return $this->hasMany(
            DetailPurchaseOrder::class,
            'id_purchase_order',
            'id_purchase_order'
        );
    }

    public function penerimaanBarang()
    {
        return $this->hasMany(PenerimaanBarang::class, 'id_purchase_order');
    }

    public function invoice()
    {
        return $this->hasOne(InvoicePembelian::class, 'id_purchase_order', 'id_purchase_order');
    }

    public function invoices()
    {
        return $this->hasMany(
            InvoicePembelian::class,
            'id_purchase_order',
            'id_purchase_order'
        );
    }
}
