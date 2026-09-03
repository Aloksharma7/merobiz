<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfitAllocation extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'profit_period_id', 'user_id', 'effective_profit_share_percent',
        'allocated_amount', 'distributed_amount',
    ];

    protected function casts(): array
    {
        return [
            'effective_profit_share_percent' => 'decimal:4',
            'allocated_amount' => 'decimal:2',
            'distributed_amount' => 'decimal:2',
        ];
    }

    public function profitPeriod(): BelongsTo
    {
        return $this->belongsTo(ProfitPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class);
    }
}
