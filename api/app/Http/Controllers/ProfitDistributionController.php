<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreProfitDistributionRequest;
use App\Http\Resources\ProfitDistributionResource;
use App\Models\Business;
use App\Models\ProfitDistribution;
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

    public function update(StoreProfitDistributionRequest $request, Business $business, ProfitDistribution $distribution): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless(in_array($membership->role, [BusinessRole::Owner, BusinessRole::Admin], true), 403);
        abort_unless($distribution->business_id === $business->id, 404);

        $updated = $this->distributions->updateDistribution($business, $distribution, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Profit distribution updated.',
            'distribution' => new ProfitDistributionResource($updated),
        ]);
    }

    public function destroy(Request $request, Business $business, ProfitDistribution $distribution): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless(in_array($membership->role, [BusinessRole::Owner, BusinessRole::Admin], true), 403);
        abort_unless($distribution->business_id === $business->id, 404);

        $this->distributions->deleteDistribution($business, $distribution, $request->user());

        return response()->json(['message' => 'Profit distribution deleted.']);
    }
}
