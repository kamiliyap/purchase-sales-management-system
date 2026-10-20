<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturPenjualan extends Model
{
    use \App\Models\Concerns\HasDocumentNumber;

    protected $table = 'retur_penjualan';
    protected $primaryKey = 'id_retur_penjualan';

    protected $fillable = [
        'id_invoice_penjualan',
        'no_retur',
        'tanggal_retur',
        'alasan',
        'status',
    ];

    public function invoicePenjualan()
    {
        return $this->belongsTo(
            InvoicePenjualan::class,
            'id_invoice_penjualan',
            'id_invoice_penjualan'
        );
    }
}
