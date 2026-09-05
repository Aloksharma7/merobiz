<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\CloseProfitPeriodRequest;
use App\Http\Resources\ProfitPeriodResource;
use App\Models\Business;
use App\Services\ProfitClosingService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfitPeriodController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly ProfitClosingService $closing) {}

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'reports.view');

        $periods = $business->profitPeriods()
            ->with(['closedBy:id,name', 'allocations.user:id,name'])
            ->latest('end_date')
            ->paginate((int) $request->query('per_page', 12));

        return ProfitPeriodResource::collection($periods);
    }

    public function store(CloseProfitPeriodRequest $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless(in_array($membership->role, [BusinessRole::Owner, BusinessRole::Admin], true), 403);

        $period = $this->closing->close($business, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Profit period closed and partner allocations created.',
            'period' => new ProfitPeriodResource($period),
        ], 201);
    }
}
