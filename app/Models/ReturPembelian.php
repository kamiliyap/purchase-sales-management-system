<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;

class ReturPembelian extends Model
{
    use HasDocumentNumber;

    protected $table = 'retur_pembelian';

    protected $primaryKey = 'id_retur_pembelian';

    protected $fillable = [
        'id_invoice_pembelian',
        'no_retur',
        'tanggal_retur',
        'alasan',
        'status',
        'id_penerimaan', 'created_by', 'approved_by', 'shipped_by', 'tanggal_dikirim', 'subtotal', 'diskon', 'ppn', 'total_retur',
    ];

    protected function casts(): array
    {
        return array_fill_keys(['subtotal', 'diskon', 'ppn', 'total_retur'], 'decimal:2');
    }

    public function penerimaanBarang()
    {
        return $this->belongsTo(PenerimaanBarang::class, 'id_penerimaan');
    }

    public function details()
    {
        return $this->hasMany(DetailReturPembelian::class, 'id_retur_pembelian');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penyetuju()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pengirim()
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function invoicePembelian()
    {
        return $this->belongsTo(
            InvoicePembelian::class,
            'id_invoice_pembelian',
            'id_invoice_pembelian'
        );
    }
}
