<?php

namespace App\Models;

use App\Enums\BusinessRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'preferred_currency',
        'signature_path',
    ];

    /** @var array<int, string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_memberships')
            ->withPivot(['role', 'title', 'commission_rate', 'active', 'joined_at'])
            ->withTimestamps();
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(OwnershipPeriod::class);
    }

    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function submittedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'submitted_by');
    }

    public function profitDistributions(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class);
    }

    public function profitWithdrawals(): HasMany
    {
        return $this->hasMany(ProfitWithdrawal::class);
    }

    public function personalIncomeSources(): HasMany
    {
        return $this->hasMany(PersonalIncomeSource::class);
    }

    public function personalIncomeEntries(): HasMany
    {
        return $this->hasMany(PersonalIncomeEntry::class);
    }

    public function personalExpenses(): HasMany
    {
        return $this->hasMany(PersonalExpense::class);
    }

    public function writer(): HasOne
    {
        return $this->hasOne(Writer::class);
    }

    /** @return array{mode: string, business_id?: int, business_name?: string, business_code?: string, writer_id?: int} */
    public function resolveWorkspace(): array
    {
        $activeMemberships = $this->memberships()->where('active', true);
        $hasPortfolioRole = (clone $activeMemberships)
            ->whereIn('role', [BusinessRole::Owner->value, BusinessRole::Admin->value])
            ->exists();

        if ($hasPortfolioRole) {
            return ['mode' => 'portfolio'];
        }

        $membership = (clone $activeMemberships)
            ->where('role', BusinessRole::Employee->value)
            ->with('business')
            ->orderBy('id')
            ->first();

        if ($membership) {
            return [
                'mode' => 'employee',
                'business_id' => $membership->business_id,
                'business_name' => $membership->business?->name,
                'business_code' => $membership->business?->code,
            ];
        }

        // A writer never has a BusinessMembership — their login is a linked User
        // record on their roster entry, so this is checked as a distinct fallback
        // rather than being folded into the membership query above.
        $writer = $this->writer()->with('business')->first();
        if ($writer) {
            return [
                'mode' => 'writer',
                'writer_id' => $writer->id,
                'business_id' => $writer->business_id,
                'business_name' => $writer->business?->name,
                'business_code' => $writer->business?->code,
            ];
        }

        return ['mode' => 'portfolio'];
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            return collect(preg_split('/\s+/', trim($this->name)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        });
    }
}
