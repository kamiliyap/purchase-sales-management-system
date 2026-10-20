<?php

namespace App\Http\Requests;

use App\Support\SalesAmounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('access-route', $this->route()->getName());
    }

    public function rules(): array
    {
        return [
            'id_customer' => ['required', 'integer', 'exists:m_customer,id_customer'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'status' => ['prohibited'],
            ...SalesAmounts::rules(),
        ];
    }

    public function messages(): array
    {
        return ['items.*.id_barang.distinct' => 'Barang yang sama tidak boleh dimasukkan dua kali dalam satu Sales Order.'];
    }
}
