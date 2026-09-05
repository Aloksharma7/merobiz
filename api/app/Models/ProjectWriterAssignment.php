<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectWriterAssignment extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'project_id', 'writer_id', 'assigned_from', 'assigned_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'date:Y-m-d',
            'assigned_to' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }
}
