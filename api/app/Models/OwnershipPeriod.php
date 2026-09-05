<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipPeriod extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'user_id', 'ownership_percent', 'profit_share_percent',
        'effective_from', 'effective_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ownership_percent' => 'decimal:4',
            'profit_share_percent' => 'decimal:4',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
