<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseProfitPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
