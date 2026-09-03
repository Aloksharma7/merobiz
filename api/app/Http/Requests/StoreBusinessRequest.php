<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:20'],
            'business_type' => ['required', Rule::in(['product', 'service', 'digital_subscription', 'mixed'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'pan_number' => ['nullable', 'string', 'max:30'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ownership_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'profit_share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
