<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class BusinessListingService
{
    /** @return Collection<int, Business> */
    public function visibleFor(User $user): Collection
    {
        $activeMemberships = $user->memberships()->where('active', true);
        $hasPortfolioRole = (clone $activeMemberships)
            ->whereIn('role', [BusinessRole::Owner->value, BusinessRole::Admin->value])
            ->exists();

        $employeeBusinessId = $hasPortfolioRole
            ? null
            : (clone $activeMemberships)
                ->where('role', BusinessRole::Employee->value)
                ->orderBy('id')
                ->value('business_id');

        return Business::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('active', true))
            ->when($employeeBusinessId, fn ($query) => $query->whereKey($employeeBusinessId))
            // BusinessResource resolves the caller's own membership/ownership per business —
            // eager-loading just this user's rows here means those resolvers find them
            // already loaded instead of firing one extra query per business in the list.
            ->with([
                'memberships' => fn ($query) => $query->where('user_id', $user->id),
                'ownerships' => fn ($query) => $query->where('user_id', $user->id)->orderByDesc('effective_from'),
            ])
            ->orderBy('name')
            ->get();
    }
}
