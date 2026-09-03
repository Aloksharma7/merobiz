<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitPeriodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'net_sales' => (float) $this->net_sales,
            'tax_collected' => (float) $this->tax_collected,
            'cost_of_sales' => (float) $this->cost_of_sales,
            'gross_profit' => (float) $this->gross_profit,
            'expenses' => (float) $this->expenses,
            'commissions' => (float) $this->commissions,
            'net_profit' => (float) $this->net_profit,
            'notes' => $this->notes,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->whenLoaded('closedBy', fn () => [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ]),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'user_id' => $allocation->user_id,
                'name' => $allocation->user->name,
                'effective_profit_share_percent' => (float) $allocation->effective_profit_share_percent,
                'allocated_amount' => (float) $allocation->allocated_amount,
                'distributed_amount' => (float) $allocation->distributed_amount,
                'remaining_amount' => round((float) $allocation->allocated_amount - (float) $allocation->distributed_amount, 2),
            ])->values()),
        ];
    }
}
