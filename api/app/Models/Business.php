<?php

namespace App\Models;

use App\Enums\BusinessCategory;
use App\Enums\ProductBusinessType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'owner_id', 'name', 'slug', 'code', 'business_type', 'category', 'product_type', 'currency',
        'pan_number', 'vat_number', 'phone', 'email', 'address',
        'invoice_prefix', 'invoice_next_number', 'payment_next_number',
        'default_tax_rate', 'status', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'category' => BusinessCategory::class,
            'product_type' => ProductBusinessType::class,
            'invoice_next_number' => 'integer',
            'payment_next_number' => 'integer',
            'default_tax_rate' => 'decimal:2',
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(OwnershipPeriod::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function profitPeriods(): HasMany
    {
        return $this->hasMany(ProfitPeriod::class);
    }

    public function profitDistributions(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class);
    }

    public function profitWithdrawals(): HasMany
    {
        return $this->hasMany(ProfitWithdrawal::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function writers(): HasMany
    {
        return $this->hasMany(Writer::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function projectProfitApprovals(): HasMany
    {
        return $this->hasMany(ProjectProfitApproval::class);
    }

    public function isInstallment(): bool
    {
        return $this->category === BusinessCategory::Installment;
    }

    public function membershipFor(?User $user): ?BusinessMembership
    {
        if (! $user) {
            return null;
        }

        if ($this->relationLoaded('memberships')) {
            return $this->memberships->firstWhere('user_id', $user->id);
        }

        return $this->memberships()->where('user_id', $user->id)->first();
    }

    public function currentOwnershipFor(User $user, CarbonInterface|string|null $asOf = null): ?OwnershipPeriod
    {
        $date = $asOf ? CarbonImmutable::parse($asOf) : CarbonImmutable::now();

        if ($this->relationLoaded('ownerships')) {
            return $this->ownerships
                ->where('user_id', $user->id)
                ->sortByDesc('effective_from')
                ->first(function (OwnershipPeriod $period) use ($date): bool {
                    $from = CarbonImmutable::parse($period->effective_from);
                    $to = $period->effective_to ? CarbonImmutable::parse($period->effective_to) : null;

                    return $from->lessThanOrEqualTo($date) && (! $to || $to->greaterThanOrEqualTo($date));
                });
        }

        return $this->ownerships()
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function isDateClosed(CarbonInterface|string $date): bool
    {
        $value = $date instanceof CarbonInterface ? $date->toDateString() : CarbonImmutable::parse($date)->toDateString();

        return $this->profitPeriods()
            ->whereDate('start_date', '<=', $value)
            ->whereDate('end_date', '>=', $value)
            ->exists();
    }

    public function hasFeature(string $key): bool
    {
        return (bool) data_get($this->settings, "features.{$key}", false);
    }
}
