<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProfitApproval extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'project_id', 'business_id', 'approved_by', 'approved_on', 'amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'approved_on' => 'date:Y-m-d',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
