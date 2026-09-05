<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreProfitDistributionRequest;
use App\Http\Resources\ProfitDistributionResource;
use App\Models\Business;
use App\Services\ProfitDistributionService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfitDistributionController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly ProfitDistributionService $distributions) {}

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'reports.view');

        $rows = $business->profitDistributions()
            ->with(['user:id,name', 'recorder:id,name', 'allocation.profitPeriod'])
            ->latest('distribution_date')
            ->paginate((int) $request->query('per_page', 20));

        return ProfitDistributionResource::collection($rows);
    }

    public function store(StoreProfitDistributionRequest $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless(in_array($membership->role, [BusinessRole::Owner, BusinessRole::Admin], true), 403);

        $distribution = $this->distributions->record($business, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Profit distribution recorded.',
            'distribution' => new ProfitDistributionResource($distribution),
        ], 201);
    }
}
