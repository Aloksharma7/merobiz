<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'submitted_by', 'approved_by', 'category', 'vendor',
        'expense_date', 'amount', 'tax_amount', 'payment_method', 'status',
        'reference', 'notes', 'approved_at', 'affects_profit',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'expense_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'affects_profit' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
