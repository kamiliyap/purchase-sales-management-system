<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturPembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['Admin', 'Purchasing'], true);
    }

    public function rules(): array
    {
        return [
            'id_penerimaan' => ['required', 'integer', 'exists:penerimaan_barang,id_penerimaan'],
            'tanggal_retur' => ['required', 'date_format:Y-m-d'],
            'alasan' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['Diajukan'])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_detail_penerimaan' => ['required', 'integer', 'exists:detail_penerimaan_barang,id_detail_penerimaan'],
            'items.*.id_detail_purchase_order' => ['required', 'integer', 'exists:detail_purchase_order,id_detail_purchase_order'],
            'items.*.kuantitas' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'items.*.alasan' => ['required', 'string', 'max:1000'],
        ];
    }
}
