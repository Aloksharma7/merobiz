<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $request->attributes->get('business_membership');
        $canSeeFinancials = $membership?->allows('dashboard.financial') ?? false;

        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status->value,
            'customer_name' => $this->customer_name ?: $this->customer?->name ?: 'Walk-in Customer',
            'customer_phone' => $this->customer_phone ?: $this->customer?->phone,
            'customer_email' => $this->customer_email ?: $this->customer?->email,
            'customer_address' => $this->customer_address ?: $this->customer?->address,
            'customer_pan_number' => $this->customer_pan_number ?: $this->customer?->pan_number,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
                'pan_number' => $this->customer->pan_number,
                'address' => $this->customer->address,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'initials' => $this->creator->initials,
            ]),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'cost_amount' => $canSeeFinancials ? (float) $this->cost_amount : null,
            'commission_amount' => $canSeeFinancials ? (float) $this->commission_amount : null,
            'paid_amount' => (float) $this->paid_amount,
            'balance_amount' => (float) $this->balance_amount,
            'notes' => $this->notes,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
