<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $request->attributes->get('business_membership');
        $canSeeFinancials = $membership?->allows('dashboard.financial') ?? false;

        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'type' => $this->type->value,
            'unit' => $this->unit,
            'sale_price' => (float) $this->sale_price,
            'cost_price' => $canSeeFinancials ? (float) $this->cost_price : null,
            'tax_rate' => (float) $this->tax_rate,
            'track_inventory' => $this->track_inventory,
            'stock_quantity' => (float) $this->stock_quantity,
            'reorder_level' => (float) $this->reorder_level,
            'active' => $this->active,
            'metadata' => $this->metadata ?? [],
            'allows_multiple_writers' => $this->allows_multiple_writers,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
