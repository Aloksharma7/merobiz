<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterPayment extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'project_id', 'writer_id', 'recorded_by', 'paid_on', 'amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
