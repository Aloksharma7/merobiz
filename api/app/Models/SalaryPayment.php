<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SalaryEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'membership_id', 'recorded_by', 'payment_date', 'amount', 'entry_type', 'method', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'entry_type' => SalaryEntryType::class,
            'method' => PaymentMethod::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(BusinessMembership::class, 'membership_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
