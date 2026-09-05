<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalIncomeEntry extends Model
{
    /** @var array<int, string> */
    protected $fillable = ['user_id', 'source_id', 'entry_date', 'amount', 'notes'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PersonalIncomeSource::class, 'source_id');
    }
}
