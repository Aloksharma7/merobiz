<?php

namespace App\Models;

use App\Enums\BusinessRole;
use App\Enums\PayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessMembership extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'business_id', 'user_id', 'role', 'full_control', 'title', 'commission_rate', 'active', 'joined_at',
        'pay_type', 'salary_amount', 'salary_visible_to_staff',
    ];

    protected function casts(): array
    {
        return [
            'role' => BusinessRole::class,
            'full_control' => 'boolean',
            'commission_rate' => 'decimal:4',
            'active' => 'boolean',
            'joined_at' => 'date:Y-m-d',
            'pay_type' => PayType::class,
            'salary_amount' => 'decimal:2',
            'salary_visible_to_staff' => 'boolean',
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

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class, 'membership_id');
    }

    public function allows(string $permission): bool
    {
        $permissions = $this->effectivePermissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /** @return array<int, string> */
    public function effectivePermissions(): array
    {
        if ($this->role === BusinessRole::Owner && ! $this->full_control) {
            return BusinessRole::Admin->permissions();
        }

        return $this->role->permissions();
    }
}
