<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePenerimaanBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['Admin', 'Purchasing'], true);
    }

    public function rules(): array
    {
        return [
            'id_purchase_order' => ['required', 'integer', 'exists:purchase_order,id_purchase_order'],
            'tanggal_penerimaan' => ['required', 'date_format:Y-m-d'],
            'id_penerima' => ['sometimes', 'required', 'integer', 'exists:users,id', Rule::in([$this->user()->id])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_barang' => ['required', 'integer', 'exists:m_barang,id_barang'],
            'items.*.kuantitas' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.kondisi' => ['required', Rule::in(['Baik', 'Rusak'])],
            'items.*.id_detail_retur_asal' => ['nullable', 'integer', 'exists:detail_retur_pembelian,id_detail_retur'],
            'items.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
