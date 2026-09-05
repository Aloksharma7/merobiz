<?php

namespace App\Support;

use App\Models\InvoiceInstallment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class InstallmentSchedule
{
    /**
     * @param  Collection<int, InvoiceInstallment>  $installments
     * @return array<int, array<string, mixed>>
     */
    public static function annotate(Collection $installments, string $paidAmount): array
    {
        $remainingPaid = Decimal::of($paidAmount);
        $today = CarbonImmutable::today();

        return $installments
            ->sortBy('sequence')
            ->values()
            ->map(function ($installment) use (&$remainingPaid, $today): array {
                $amount = Decimal::of($installment->amount);
                $allocated = $remainingPaid->isGreaterThan(0)
                    ? ($remainingPaid->isGreaterThanOrEqualTo($amount) ? $amount : $remainingPaid)
                    : Decimal::of(0);
                $remainingPaid = $remainingPaid->minus($allocated);

                $status = match (true) {
                    $allocated->isGreaterThanOrEqualTo($amount) => 'paid',
                    $allocated->isGreaterThan(0) => 'partial',
                    $installment->due_date->lt($today) => 'overdue',
                    default => 'pending',
                };

                return [
                    'id' => $installment->id,
                    'sequence' => $installment->sequence,
                    'due_date' => $installment->due_date->toDateString(),
                    'amount' => (float) Decimal::money($amount),
                    'paid_amount' => (float) Decimal::money($allocated),
                    'remaining_amount' => (float) Decimal::money($amount->minus($allocated)),
                    'status' => $status,
                    'notes' => $installment->notes,
                ];
            })
            ->all();
    }
}
