<?php

namespace App\Models;

use App\Enums\ProfitPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfitPeriod extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'start_date', 'end_date', 'status', 'net_sales', 'tax_collected',
        'cost_of_sales', 'gross_profit', 'expenses', 'commissions', 'payroll_cost', 'writer_cost', 'net_profit',
        'closed_by', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => ProfitPeriodStatus::class,
            'net_sales' => 'decimal:2',
            'tax_collected' => 'decimal:2',
            'cost_of_sales' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'expenses' => 'decimal:2',
            'commissions' => 'decimal:2',
            'payroll_cost' => 'decimal:2',
            'writer_cost' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProfitAllocation::class);
    }
}
