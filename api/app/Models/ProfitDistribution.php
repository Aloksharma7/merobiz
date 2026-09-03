<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitDistribution extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'profit_allocation_id', 'business_id', 'user_id', 'recorded_by',
        'distribution_date', 'amount', 'method', 'reference', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'distribution_date' => 'date',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
        ];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ProfitAllocation::class, 'profit_allocation_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
