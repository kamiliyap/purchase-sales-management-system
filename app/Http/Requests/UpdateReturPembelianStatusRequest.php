<?php

namespace App\Http\Requests;

use App\Support\PurchasingStatuses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturPembelianStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['Admin', 'Purchasing'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(PurchasingStatuses::RETURN)],
            'tanggal_dikirim' => ['required_if:status,Selesai', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
