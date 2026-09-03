<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:180'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
