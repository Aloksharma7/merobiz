<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRefund extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'project_id', 'business_id', 'refunded_by', 'refunded_on', 'amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'refunded_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
