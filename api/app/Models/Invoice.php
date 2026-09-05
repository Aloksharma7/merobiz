<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\SaleVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'customer_id', 'project_id', 'customer_name', 'customer_phone', 'customer_email',
        'customer_address', 'customer_pan_number', 'created_by', 'invoice_number', 'invoice_date',
        'due_date', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'cost_amount', 'commission_amount', 'paid_amount', 'balance_amount', 'notes', 'finalized_at',
        'verification_status', 'verified_by', 'verified_at', 'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'status' => InvoiceStatus::class,
            'verification_status' => SaleVerificationStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'finalized_at' => 'datetime',
            'verified_at' => 'datetime',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class);
    }
}
