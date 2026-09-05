<?php

namespace App\Http\Requests;

use App\Enums\ProjectWorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_name' => ['sometimes', 'required', 'string', 'max:180'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:180'],
            'started_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'topic' => ['sometimes', 'required', 'string', 'max:4000'],
            'course' => ['sometimes', 'required', 'string', 'max:180'],
            'work' => ['sometimes', 'required', 'string', 'max:180'],
            'work_status' => ['sometimes', Rule::enum(ProjectWorkStatus::class)],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'deal_amount' => ['sometimes', 'numeric', 'min:0'],
            'writer_payment_amount' => ['sometimes', 'numeric', 'min:0'],
            'writer_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
