<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitWithdrawal extends Model
{
    /** @var array<int, string> */
    protected $fillable = ['business_id', 'user_id', 'withdrawn_on', 'amount', 'notes'];

    protected function casts(): array
    {
        return [
            'withdrawn_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
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
