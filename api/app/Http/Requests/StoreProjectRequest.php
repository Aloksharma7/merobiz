<?php

namespace App\Http\Requests;

use App\Enums\ProjectWorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'client_name' => ['required', 'string', 'max:180'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:180'],
            'started_on' => ['nullable', 'date', 'before_or_equal:today'],
            'topic' => ['required', 'string', 'max:4000'],
            'course' => ['required', 'string', 'max:180'],
            'work' => ['required', 'string', 'max:180'],
            'work_status' => ['sometimes', Rule::enum(ProjectWorkStatus::class)],
            'deadline' => ['nullable', 'date'],
            'deal_amount' => ['required', 'numeric', 'min:0'],
            'writer_payment_amount' => ['sometimes', 'numeric', 'min:0'],
            'writer_id' => ['nullable', 'integer'],
        ];
    }
}
