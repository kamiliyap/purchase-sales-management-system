<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

class InvoicePembelian extends Model
{
    use HasDocumentNumber;

    protected $table = 'invoice_pembelian';

    protected $primaryKey = 'id_invoice_pembelian';

    protected $fillable = [
        'id_purchase_order',
        'no_invoice',
        'tanggal_invoice',
        'jatuh_tempo',
        'total_invoice',
        'status',
        'dibuat_otomatis',
    ];

    protected function casts(): array
    {
        return ['dibuat_otomatis' => 'boolean'];
    }

    public function getSumberAttribute(): string
    {
        return $this->dibuat_otomatis ? 'Otomatis oleh sistem' : 'Historis';
    }

    public function getNomorPenerimaanAttribute(): string
    {
        return $this->purchaseOrder->penerimaanBarang->pluck('no_penerimaan')->join(', ');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(
            PurchaseOrder::class,
            'id_purchase_order',
            'id_purchase_order'
        );
    }

    public function returPembelian()
    {
        return $this->hasMany(
            ReturPembelian::class,
            'id_invoice_pembelian',
            'id_invoice_pembelian'
        );
    }
}
