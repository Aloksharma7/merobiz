<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date->toDateString(),
            'amount' => (float) $this->amount,
            'method' => $this->method->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'recorded_by' => $this->whenLoaded('recorder', fn () => [
                'id' => $this->recorder->id,
                'name' => $this->recorder->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
