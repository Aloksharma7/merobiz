<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'category' => $this->category,
            'vendor' => $this->vendor,
            'expense_date' => $this->expense_date->toDateString(),
            'amount' => (float) $this->amount,
            'tax_amount' => (float) $this->tax_amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'submitter' => $this->whenLoaded('submitter', fn () => $this->submitter ? [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
