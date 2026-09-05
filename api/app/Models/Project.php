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

    public function collectedAmount(): float
    {
        return (float) $this->invoices()->whereIn('status', self::LIVE_INVOICE_STATUSES)->sum('paid_amount');
    }

    public function dueAmount(): float
    {
        // Each sale is its own payment against the deal amount (immediately paid in full),
        // so due is tracked against the deal total rather than any single invoice's balance.
        return max(0.0, (float) $this->deal_amount - $this->collectedAmount());
    }

    public function writerPaidAmount(): float
    {
        return (float) $this->writerPayments()->sum('amount');
    }

    public function writerDueAmount(): float
    {
        return max(0.0, (float) $this->writer_payment_amount - $this->writerPaidAmount());
    }

    public function approvedProfitTotal(): float
    {
        return (float) $this->profitApprovals()->sum('amount');
    }

    public function currentWriterAssignment(): ?ProjectWriterAssignment
    {
        return $this->writerAssignments()->whereNull('assigned_to')->with('writer')->first();
    }

    public function currentWriter(): ?Writer
    {
        return $this->currentWriterAssignment()?->writer;
    }
}
