<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
