<?php

namespace App\Http\Requests;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([ExpenseStatus::Approved->value, ExpenseStatus::Rejected->value])],
        ];
    }
}
