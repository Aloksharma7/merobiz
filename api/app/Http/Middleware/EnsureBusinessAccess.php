<?php

namespace App\Http\Middleware;

use App\Enums\BusinessRole;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');

        if (! $business instanceof Business) {
            $business = Business::query()->findOrFail($business);
            $request->route()->setParameter('business', $business);
        }

        $membership = $business->memberships()
            ->where('user_id', $request->user()->id)
            ->where('active', true)
            ->first();

        abort_unless($membership, 403, 'You do not have access to this business.');

        // A pure employee account has one active company workspace. Even if an old
        // database accidentally contains more than one employee membership, only
        // the first active assignment is reachable until an owner/admin fixes it.
        if ($membership->role === BusinessRole::Employee) {
            $hasPortfolioRole = $request->user()->memberships()
                ->where('active', true)
                ->whereIn('role', [BusinessRole::Owner->value, BusinessRole::Admin->value])
                ->exists();

            if (! $hasPortfolioRole) {
                $primaryEmployeeMembershipId = $request->user()->memberships()
                    ->where('active', true)
                    ->where('role', BusinessRole::Employee->value)
                    ->orderBy('id')
                    ->value('id');

                abort_unless($membership->id === $primaryEmployeeMembershipId, 403, 'This employee account is assigned to a different business.');
            }
        }

        $request->attributes->set('business_membership', $membership);

        return $next($request);
    }
}
