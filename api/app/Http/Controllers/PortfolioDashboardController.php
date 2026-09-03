<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Services\DashboardService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioDashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $memberships = $request->user()->memberships()->where('active', true)->get(['role']);
        $employeeOnly = $memberships->isNotEmpty()
            && ! $memberships->contains(fn ($membership): bool => in_array($membership->role, [BusinessRole::Owner, BusinessRole::Admin], true));

        abort_if($employeeOnly, 403, 'The portfolio dashboard is not available to employee accounts.');

        return response()->json($this->dashboard->portfolio($request->user(), DateRange::fromRequest($request)));
    }
}
