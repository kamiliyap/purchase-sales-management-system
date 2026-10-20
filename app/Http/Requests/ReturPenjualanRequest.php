<?php

namespace App\Http\Requests;

use App\Support\SalesStatuses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReturPenjualanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('access-route', $this->route()->getName());
    }

    public function rules(): array
    {
        return [
            'id_invoice_penjualan' => ['required', 'integer', 'exists:invoice_penjualan,id_invoice_penjualan'],
            'tanggal_retur' => ['required', 'date_format:Y-m-d'],
            'alasan' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(SalesStatuses::RETURN)],
        ];
    }
}
