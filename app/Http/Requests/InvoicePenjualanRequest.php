<?php

namespace App\Http\Requests;

use App\Support\SalesStatuses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoicePenjualanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('access-route', $this->route()->getName());
    }

    public function rules(): array
    {
        return [
            'id_sales_order' => ['required', 'integer', 'exists:sales_order,id_sales_order'],
            'tanggal_invoice' => ['required', 'date_format:Y-m-d'],
            'jatuh_tempo' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_invoice'],
            'status' => ['required', Rule::in(SalesStatuses::INVOICE)],
        ];
    }
}
