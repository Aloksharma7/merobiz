<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\SalaryEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            // write_off is never created through this endpoint — it has its own
            // route because it doesn't represent new money changing hands.
            'entry_type' => ['sometimes', Rule::in([SalaryEntryType::Payment->value, SalaryEntryType::Advance->value, SalaryEntryType::Loan->value])],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
