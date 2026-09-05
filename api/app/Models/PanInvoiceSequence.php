<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanInvoiceSequence extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'pan_number', 'next_number',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
