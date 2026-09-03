<?php

namespace App\Support;

use App\Models\BusinessMembership;
use Illuminate\Http\Request;

trait AuthorizesBusinessActions
{
    protected function membership(Request $request): BusinessMembership
    {
        $membership = $request->attributes->get('business_membership');

        abort_unless($membership instanceof BusinessMembership, 403, 'Business access is required.');

        return $membership;
    }

    protected function requirePermission(Request $request, string $permission): BusinessMembership
    {
        $membership = $this->membership($request);

        abort_unless($membership->allows($permission), 403, 'You do not have permission to perform this action.');

        return $membership;
    }
}
