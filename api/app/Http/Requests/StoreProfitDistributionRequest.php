<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfitDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'profit_allocation_id' => ['required', 'integer'],
            'distribution_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
