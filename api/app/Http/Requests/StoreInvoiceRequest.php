<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'customer_name' => ['nullable', 'string', 'max:150', 'required_without:customer_id'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_pan_number' => ['nullable', 'string', 'max:30'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'status' => ['sometimes', Rule::in([InvoiceStatus::Draft->value, InvoiceStatus::Issued->value])],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
