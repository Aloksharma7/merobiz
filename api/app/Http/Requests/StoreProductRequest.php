<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sku' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'unit' => ['sometimes', 'string', 'max:30'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'track_inventory' => ['sometimes', 'boolean'],
            'stock_quantity' => ['sometimes', 'numeric', 'min:0'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
