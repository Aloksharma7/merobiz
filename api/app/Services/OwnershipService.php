<?php

namespace App\Services;

use App\Models\Business;
use App\Models\OwnershipPeriod;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnershipService
{
    public function __construct(private readonly AuditService $audit) {}

    public function schedule(
        Business $business,
        int $targetUserId,
        float $ownershipPercent,
        float $profitSharePercent,
        CarbonImmutable $effectiveFrom,
        ?string $notes,
        User $actor,
        ?Request $request = null,
    ): OwnershipPeriod {
        abort_unless($business->memberships()->where('user_id', $targetUserId)->where('active', true)->exists(), 422, 'The selected member must be an active business member.');

        $lastClosedDate = $business->profitPeriods()->max('end_date');
        if ($lastClosedDate && $effectiveFrom->lte(CarbonImmutable::parse($lastClosedDate))) {
            throw ValidationException::withMessages([
                'effective_from' => 'Ownership cannot be changed inside a closed profit period.',
            ]);
        }

        $futureRecordExists = $business->ownerships()
            ->where('user_id', $targetUserId)
            ->whereDate('effective_from', '>', $effectiveFrom->toDateString())
            ->exists();
        if ($futureRecordExists) {
            throw ValidationException::withMessages([
                'effective_from' => 'Remove or revise the later scheduled ownership record first.',
            ]);
        }

        $overlapping = fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString());

        $otherOwnership = $business->ownerships()
            ->where('user_id', '!=', $targetUserId)
            ->whereDate('effective_from', '<=', $effectiveFrom->toDateString())
            ->where($overlapping)
            ->sum('ownership_percent');

        $otherProfitShare = $business->ownerships()
            ->where('user_id', '!=', $targetUserId)
            ->whereDate('effective_from', '<=', $effectiveFrom->toDateString())
            ->where($overlapping)
            ->sum('profit_share_percent');

        if (Decimal::of($otherOwnership)->plus(Decimal::of($ownershipPercent))->isGreaterThan(100)) {
            throw ValidationException::withMessages(['ownership_percent' => 'Combined ownership cannot exceed 100%.']);
        }
        if (Decimal::of($otherProfitShare)->plus(Decimal::of($profitSharePercent))->isGreaterThan(100)) {
            throw ValidationException::withMessages(['profit_share_percent' => 'Combined profit sharing cannot exceed 100%.']);
        }

        return DB::transaction(function () use ($business, $targetUserId, $ownershipPercent, $profitSharePercent, $effectiveFrom, $notes, $actor, $request): OwnershipPeriod {
            $current = $business->ownerships()
                ->where('user_id', $targetUserId)
                ->whereDate('effective_from', '<=', $effectiveFrom->toDateString())
                ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom->toDateString()))
                ->lockForUpdate()
                ->first();

            if ($current) {
                if ($current->effective_from->isSameDay($effectiveFrom)) {
                    $before = $current->toArray();
                    $current->update([
                        'ownership_percent' => $ownershipPercent,
                        'profit_share_percent' => $profitSharePercent,
                        'notes' => $notes ?? $current->notes,
                    ]);
                    $this->audit->record($actor, $business, 'ownership.updated', $current, $before, $current->fresh()->toArray(), $request);

                    return $current->fresh('user');
                }

                $current->update(['effective_to' => $effectiveFrom->subDay()->toDateString()]);
            }

            $created = $business->ownerships()->create([
                'user_id' => $targetUserId,
                'ownership_percent' => $ownershipPercent,
                'profit_share_percent' => $profitSharePercent,
                'effective_from' => $effectiveFrom->toDateString(),
                'notes' => $notes,
            ]);
            $created->load('user');
            $this->audit->record($actor, $business, 'ownership.changed', $created, null, $created->toArray(), $request);

            return $created;
        });
    }
}
