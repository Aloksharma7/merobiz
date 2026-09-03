<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'cost_price' => (float) $this->cost_price,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
        ];
    }
}
