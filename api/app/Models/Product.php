<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'sku', 'name', 'type', 'unit', 'sale_price', 'cost_price',
        'tax_rate', 'track_inventory', 'stock_quantity', 'reorder_level', 'active', 'metadata',
        'allows_multiple_writers',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'track_inventory' => 'boolean',
            'stock_quantity' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'active' => 'boolean',
            'metadata' => 'array',
            'allows_multiple_writers' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
