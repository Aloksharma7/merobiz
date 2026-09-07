<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreOwnershipRequest;
use App\Models\Business;
use App\Models\OwnershipPeriod;
use App\Services\OwnershipService;
use App\Support\AuthorizesBusinessActions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnershipController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly OwnershipService $ownership) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless($membership->role === BusinessRole::Owner, 403);

        $periods = $business->ownerships()
            ->with('user:id,name,email')
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (OwnershipPeriod $period): array => [
                'id' => $period->id,
                'user_id' => $period->user_id,
                'name' => $period->user->name,
                'email' => $period->user->email,
                'ownership_percent' => (float) $period->ownership_percent,
                'profit_share_percent' => (float) $period->profit_share_percent,
                'effective_from' => $period->effective_from->toDateString(),
                'effective_to' => $period->effective_to?->toDateString(),
                'notes' => $period->notes,
                'current' => $period->effective_from->lte(today()) && ($period->effective_to === null || $period->effective_to->gte(today())),
            ]);

        return response()->json(['data' => $periods]);
    }

    public function store(StoreOwnershipRequest $request, Business $business): JsonResponse
    {
        $actorMembership = $this->membership($request);
        abort_unless($actorMembership->full_control, 403, 'Only a full-control owner can change ownership stakes.');
        $data = $request->validated();
        $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        $period = $this->ownership->schedule(
            $business,
            (int) $data['user_id'],
            (float) $data['ownership_percent'],
            (float) $data['profit_share_percent'],
            $effectiveFrom,
            $data['notes'] ?? null,
            $request->user(),
            $request,
        );

        return response()->json([
            'message' => 'Ownership record saved.',
            'ownership' => [
                'id' => $period->id,
                'user_id' => $period->user_id,
                'name' => $period->user->name,
                'ownership_percent' => (float) $period->ownership_percent,
                'profit_share_percent' => (float) $period->profit_share_percent,
                'effective_from' => $period->effective_from->toDateString(),
                'effective_to' => $period->effective_to?->toDateString(),
            ],
        ], 201);
    }
}
