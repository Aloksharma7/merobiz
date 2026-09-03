<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'ownership_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'profit_share_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
