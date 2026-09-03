<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $request->attributes->get('business_membership');
        $employee = $membership?->role?->value === 'employee';

        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'pan_number' => $this->pan_number,
            'address' => $this->address,
            // Opening balance is a company-level accounting value. Employees only
            // see outstanding amounts produced by their own invoices.
            'opening_balance' => $employee ? 0.0 : (float) $this->opening_balance,
            'outstanding_balance' => round((float) ($this->getAttribute('outstanding_balance') ?? 0), 2),
            'notes' => $this->notes,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
