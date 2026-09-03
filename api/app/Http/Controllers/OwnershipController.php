<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreOwnershipRequest;
use App\Models\Business;
use App\Models\OwnershipPeriod;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnershipController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit)
    {
    }

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
        abort_unless($actorMembership->role === BusinessRole::Owner, 403);
        $data = $request->validated();

        abort_unless($business->memberships()->where('user_id', $data['user_id'])->where('active', true)->exists(), 422, 'The selected partner must be an active business member.');

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();
        $lastClosedDate = $business->profitPeriods()->max('end_date');
        if ($lastClosedDate && $effectiveFrom->lte(CarbonImmutable::parse($lastClosedDate))) {
            throw ValidationException::withMessages([
                'effective_from' => 'Ownership cannot be changed inside a closed profit period.',
            ]);
        }

        $futureRecordExists = $business->ownerships()
            ->where('user_id', $data['user_id'])
            ->whereDate('effective_from', '>', $effectiveFrom->toDateString())
            ->exists();
        if ($futureRecordExists) {
            throw ValidationException::withMessages([
                'effective_from' => 'Remove or revise the later scheduled ownership record first.',
            ]);
        }

        $otherOwnership = (float) $business->ownerships()
            ->where('user_id', '!=', $data['user_id'])
            ->whereDate('effective_from', '<=', $effectiveFrom->toDateString())
            ->where(function (Builder $query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString());
            })
            ->sum('ownership_percent');

        $otherProfitShare = (float) $business->ownerships()
            ->where('user_id', '!=', $data['user_id'])
            ->whereDate('effective_from', '<=', $effectiveFrom->toDateString())
            ->where(function (Builder $query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString());
            })
            ->sum('profit_share_percent');

        if ($otherOwnership + (float) $data['ownership_percent'] > 100.0001) {
            throw ValidationException::withMessages(['ownership_percent' => 'Combined ownership cannot exceed 100%.']);
        }
        if ($otherProfitShare + (float) $data['profit_share_percent'] > 100.0001) {
            throw ValidationException::withMessages(['profit_share_percent' => 'Combined profit sharing cannot exceed 100%.']);
        }

        $period = DB::transaction(function () use ($request, $business, $data, $effectiveFrom): OwnershipPeriod {
            $current = $business->ownerships()
                ->where('user_id', $data['user_id'])
                ->whereDate('effective_from', '<=', $effectiveFrom->toDateString())
                ->where(function (Builder $query) use ($effectiveFrom): void {
                    $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString());
                })
                ->lockForUpdate()
                ->first();

            if ($current) {
                if ($current->effective_from->isSameDay($effectiveFrom)) {
                    $before = $current->toArray();
                    $current->update([
                        'ownership_percent' => $data['ownership_percent'],
                        'profit_share_percent' => $data['profit_share_percent'],
                        'notes' => $data['notes'] ?? $current->notes,
                    ]);
                    $this->audit->record($request->user(), $business, 'ownership.updated', $current, $before, $current->fresh()->toArray(), $request);

                    return $current->fresh('user');
                }

                $current->update(['effective_to' => $effectiveFrom->subDay()->toDateString()]);
            }

            $created = $business->ownerships()->create([
                'user_id' => $data['user_id'],
                'ownership_percent' => $data['ownership_percent'],
                'profit_share_percent' => $data['profit_share_percent'],
                'effective_from' => $effectiveFrom->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
            $created->load('user');
            $this->audit->record($request->user(), $business, 'ownership.changed', $created, null, $created->toArray(), $request);

            return $created;
        });

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
