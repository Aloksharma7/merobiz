<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectWorkStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    private const LIVE_INVOICE_STATUSES = [
        InvoiceStatus::Issued->value,
        InvoiceStatus::Partial->value,
        InvoiceStatus::Paid->value,
        InvoiceStatus::Overdue->value,
    ];

    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'customer_id', 'created_by', 'client_name', 'client_phone', 'client_email', 'started_on',
        'topic', 'course', 'work', 'work_status', 'deadline', 'deal_amount', 'writer_payment_amount',
    ];

    protected function casts(): array
    {
        return [
            'work_status' => ProjectWorkStatus::class,
            'started_on' => 'date:Y-m-d',
            'deadline' => 'date:Y-m-d',
            'deal_amount' => 'decimal:2',
            'writer_payment_amount' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function writerAssignments(): HasMany
    {
        return $this->hasMany(ProjectWriterAssignment::class);
    }

    public function profitApprovals(): HasMany
    {
        return $this->hasMany(ProjectProfitApproval::class);
    }

    public function writerPayments(): HasMany
    {
        return $this->hasMany(WriterPayment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(ProjectRefund::class);
    }

    public function collectedAmount(): float
    {
        // Same reasoning as currentWriterAssignment() below — a list of many projects
        // (the projects list, a customer's file history) eager-loads invoices up
        // front, so reuse that instead of firing a SUM query per project.
        if ($this->relationLoaded('invoices')) {
            return (float) $this->invoices
                ->filter(fn (Invoice $invoice) => in_array($invoice->status->value, self::LIVE_INVOICE_STATUSES, true))
                ->sum(fn (Invoice $invoice) => (float) $invoice->paid_amount);
        }

        return (float) $this->invoices()->whereIn('status', self::LIVE_INVOICE_STATUSES)->sum('paid_amount');
    }

    public function refundedAmount(): float
    {
        if ($this->relationLoaded('refunds')) {
            return (float) $this->refunds->sum(fn (ProjectRefund $refund) => (float) $refund->amount);
        }

        return (float) $this->refunds()->sum('amount');
    }

    /** What the business actually kept after giving anything back to the client. */
    public function netCollectedAmount(): float
    {
        return max(0.0, $this->collectedAmount() - $this->refundedAmount());
    }

    public function dueAmount(): float
    {
        // A cancelled/aborted project is closed out — nothing further is owed on a
        // deal that's off, however much was or wasn't collected before it stopped.
        if ($this->work_status === ProjectWorkStatus::Cancelled) {
            return 0.0;
        }

        // Each sale is its own payment against the deal amount (immediately paid in full),
        // so due is tracked against the deal total rather than any single invoice's balance.
        return max(0.0, (float) $this->deal_amount - $this->collectedAmount());
    }

    public function writerPaidAmount(): float
    {
        if ($this->relationLoaded('writerPayments')) {
            return (float) $this->writerPayments->sum(fn (WriterPayment $payment) => (float) $payment->amount);
        }

        return (float) $this->writerPayments()->sum('amount');
    }

    public function writerDueAmount(): float
    {
        return max(0.0, (float) $this->writer_payment_amount - $this->writerPaidAmount());
    }

    public function approvedProfitTotal(): float
    {
        if ($this->relationLoaded('profitApprovals')) {
            return (float) $this->profitApprovals->sum(fn (ProjectProfitApproval $approval) => (float) $approval->amount);
        }

        return (float) $this->profitApprovals()->sum('amount');
    }

    public function currentWriterAssignment(): ?ProjectWriterAssignment
    {
        // Callers that list many projects at once (the projects list, a customer's
        // or writer's file history) eager-load writerAssignments up front — reuse
        // that instead of firing one extra query per project in the list.
        if ($this->relationLoaded('writerAssignments')) {
            return $this->writerAssignments->first(fn (ProjectWriterAssignment $assignment) => $assignment->assigned_to === null);
        }

        return $this->writerAssignments()->whereNull('assigned_to')->with('writer')->first();
    }

    public function currentWriter(): ?Writer
    {
        return $this->currentWriterAssignment()?->writer;
    }
}
