<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ProfitAllocation;
use App\Models\ProfitDistribution;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfitDistributionService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /** @param array<string, mixed> $data */
    public function record(Business $business, User $actor, array $data): ProfitDistribution
    {
        return DB::transaction(function () use ($business, $actor, $data): ProfitDistribution {
            /** @var ProfitAllocation $allocation */
            $allocation = ProfitAllocation::query()
                ->with('profitPeriod')
                ->lockForUpdate()
                ->findOrFail($data['profit_allocation_id']);

            abort_unless($allocation->profitPeriod->business_id === $business->id, 404);

            if (CarbonImmutable::parse($data['distribution_date'])->startOfDay()->lt($allocation->profitPeriod->end_date->startOfDay())) {
                throw ValidationException::withMessages(['distribution_date' => 'The distribution date cannot be before the closed period ends.']);
            }

            $remaining = Decimal::of($allocation->allocated_amount)->minus(Decimal::of($allocation->distributed_amount));
            $amount = Decimal::of($data['amount']);

            if ($amount->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['amount' => 'The distribution exceeds the remaining allocated profit.']);
            }

            if ($remaining->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages(['amount' => 'This allocation is already fully distributed.']);
            }

            $distribution = ProfitDistribution::query()->create([
                'profit_allocation_id' => $allocation->id,
                'business_id' => $business->id,
                'user_id' => $allocation->user_id,
                'recorded_by' => $actor->id,
                'distribution_date' => $data['distribution_date'],
                'amount' => Decimal::money($amount),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $allocation->update([
                'distributed_amount' => Decimal::money(Decimal::of($allocation->distributed_amount)->plus($amount)),
            ]);

            $distribution->load(['allocation.profitPeriod', 'user', 'recorder']);
            $this->audit->record($actor, $business, 'profit.distributed', $distribution, null, $distribution->toArray());

            return $distribution;
        });
    }
}
