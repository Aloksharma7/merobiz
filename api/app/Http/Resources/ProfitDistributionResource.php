<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitDistributionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profit_allocation_id' => $this->profit_allocation_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'distribution_date' => $this->distribution_date->toDateString(),
            'amount' => (float) $this->amount,
            'method' => $this->method->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
