<?php

namespace App\Services;

use App\Models\Business;
use App\Models\OwnershipPeriod;
use App\Models\ProfitPeriod;
use App\Models\User;
use App\Support\Decimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfitClosingService
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly AuditService $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function close(Business $business, User $actor, array $data): ProfitPeriod
    {
        return DB::transaction(function () use ($business, $actor, $data): ProfitPeriod {
            /** @var Business $lockedBusiness */
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->id);

            $start = CarbonImmutable::parse($data['start_date'])->startOfDay();
            $end = CarbonImmutable::parse($data['end_date'])->endOfDay();

            $overlaps = $lockedBusiness->profitPeriods()
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'start_date' => 'This date range overlaps an already closed profit period.',
                ]);
            }

            $metrics = $this->dashboard->metrics($lockedBusiness, $start, $end);

            $period = $lockedBusiness->profitPeriods()->create([
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'closed',
                'net_sales' => $metrics['net_sales'],
                'tax_collected' => $metrics['tax_collected'],
                'cost_of_sales' => $metrics['cost_of_sales'],
                'gross_profit' => $metrics['gross_profit'],
                'expenses' => $metrics['expenses'],
                'commissions' => $metrics['commissions'],
                'payroll_cost' => $metrics['payroll_cost'],
                'writer_cost' => $metrics['writer_cost'],
                'net_profit' => $metrics['net_profit'],
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($this->allocationsFor($lockedBusiness, $start, $end, $metrics['net_profit']) as $allocation) {
                $period->allocations()->create($allocation);
            }

            $period->load('allocations.user');
            $this->audit->record($actor, $lockedBusiness, 'profit.closed', $period, null, $period->toArray());

            return $period;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allocationsFor(Business $business, CarbonImmutable $start, CarbonImmutable $end, float $periodNetProfit): array
    {
        $periods = OwnershipPeriod::query()
            ->where('business_id', $business->id)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start->toDateString());
            })
            ->orderBy('user_id')
            ->orderBy('effective_from')
            ->get()
            ->groupBy('user_id');

        $allocations = [];

        foreach ($periods as $userId => $userPeriods) {
            $allocated = Decimal::of(0);
            $lastShare = '0';

            foreach ($userPeriods as $period) {
                $segmentStart = CarbonImmutable::parse($period->effective_from)->max($start);
                $segmentEnd = $period->effective_to
                    ? CarbonImmutable::parse($period->effective_to)->min($end)
                    : $end;

                if ($segmentStart->greaterThan($segmentEnd)) {
                    continue;
                }

                $segmentMetrics = $this->dashboard->metrics($business, $segmentStart, $segmentEnd);
                $segmentProfit = Decimal::of($segmentMetrics['net_profit']);
                $share = Decimal::of($period->profit_share_percent);
                $lastShare = (string) $share;

                $allocated = $allocated->plus(
                    $segmentProfit->multipliedBy($share)->dividedBy(100, 2, RoundingMode::HalfUp)
                );
            }

            $allocatedAmount = Decimal::money($allocated);
            if ((float) $allocatedAmount <= 0) {
                continue;
            }

            $effectiveShare = $periodNetProfit > 0
                ? Decimal::percentage(
                    $allocated->multipliedBy(100)->dividedBy(Decimal::of($periodNetProfit), 4, RoundingMode::HalfUp)
                )
                : Decimal::percentage($lastShare);

            $allocations[] = [
                'user_id' => $userId,
                'effective_profit_share_percent' => $effectiveShare,
                'allocated_amount' => $allocatedAmount,
                'distributed_amount' => '0.00',
            ];
        }

        return $allocations;
    }
}
