<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Writer extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'user_id', 'name', 'phone', 'email', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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

    public function assignments(): HasMany
    {
        return $this->hasMany(WriterAssignment::class);
    }

    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectWriterAssignment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WriterPayment::class);
    }
}
