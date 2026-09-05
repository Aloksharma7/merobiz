<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterAssignment extends Model
{
    protected $table = 'invoice_item_writer_assignments';

    /** @var array<int, string> */
    protected $fillable = [
        'invoice_item_id', 'writer_id', 'assigned_from', 'assigned_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'date:Y-m-d',
            'assigned_to' => 'date:Y-m-d',
        ];
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }
}
