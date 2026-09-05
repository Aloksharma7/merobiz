<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $collected = $this->collectedAmount();
        $currentAssignment = $this->currentWriterAssignment();
        $membership = $request->attributes->get('business_membership');
        $canSeeProfit = $membership?->allows('dashboard.financial') ?? false;

        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'customer_id' => $this->customer_id,
            'client_name' => $this->client_name,
            'client_phone' => $this->client_phone,
            'client_email' => $this->client_email,
            'started_on' => $this->started_on?->toDateString(),
            'topic' => $this->topic,
            'course' => $this->course,
            'work' => $this->work,
            'work_status' => $this->work_status->value,
            'deadline' => $this->deadline?->toDateString(),
            'deal_amount' => (float) $this->deal_amount,
            'writer_payment_amount' => (float) $this->writer_payment_amount,
            'collected_amount' => round($collected, 2),
            'due_amount' => round($this->dueAmount(), 2),
            'writer_paid_amount' => round($this->writerPaidAmount(), 2),
            'writer_due_amount' => round($this->writerDueAmount(), 2),
            'approved_profit_total' => $canSeeProfit ? round($this->approvedProfitTotal(), 2) : null,
            'current_writer' => $currentAssignment && $currentAssignment->writer ? [
                'id' => $currentAssignment->writer->id,
                'name' => $currentAssignment->writer->name,
                'assigned_from' => $currentAssignment->assigned_from->toDateString(),
            ] : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'invoices' => $this->whenLoaded('invoices', fn () => $this->invoices
                ->sortByDesc('invoice_date')
                ->map(fn ($invoice): array => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date->toDateString(),
                    'status' => $invoice->status->value,
                    'total_amount' => (float) $invoice->total_amount,
                    'paid_amount' => (float) $invoice->paid_amount,
                    'balance_amount' => (float) $invoice->balance_amount,
                ])->values()),
            'profit_approvals' => $canSeeProfit ? $this->whenLoaded('profitApprovals', fn () => $this->profitApprovals
                ->sortByDesc('approved_on')
                ->map(fn ($approval): array => [
                    'id' => $approval->id,
                    'approved_on' => $approval->approved_on->toDateString(),
                    'amount' => (float) $approval->amount,
                    'notes' => $approval->notes,
                ])->values()) : null,
            'writer_history' => $this->whenLoaded('writerAssignments', fn () => $this->writerAssignments
                ->sortByDesc('assigned_from')
                ->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'writer' => $assignment->relationLoaded('writer') && $assignment->writer ? ['id' => $assignment->writer->id, 'name' => $assignment->writer->name] : null,
                    'assigned_from' => $assignment->assigned_from->toDateString(),
                    'assigned_to' => $assignment->assigned_to?->toDateString(),
                    'current' => $assignment->assigned_to === null,
                ])->values()),
        ];
    }
}
