<?php

namespace App\Models;

use App\Enums\BusinessRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMembership extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'user_id', 'role', 'title', 'commission_rate', 'active', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
            'commission_rate' => 'decimal:4',
            'active' => 'boolean',
            'joined_at' => 'date',
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

    public function allows(string $permission): bool
    {
        return $this->role->allows($permission);
    }
}
