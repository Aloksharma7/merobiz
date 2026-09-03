<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\DashboardService;
use App\Support\AuthorizesBusinessActions;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessDashboardController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function __invoke(Request $request, Business $business): JsonResponse
    {
        return response()->json(
            $this->dashboard->business($business, $request->user(), $this->membership($request), DateRange::fromRequest($request))
        );
    }
}
