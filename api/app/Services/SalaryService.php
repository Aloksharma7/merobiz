<?php

namespace App\Services;

use App\Enums\PayType;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invoice;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @return array<string, mixed> */
    public function summaryFor(BusinessMembership $membership): array
    {
        $paidTotal = Decimal::of($membership->salaryPayments()->sum('amount'));

        if ($membership->pay_type === PayType::FixedSalary) {
            $monthsElapsed = $this->monthsElapsed($membership);
            $accrued = Decimal::of($membership->salary_amount)->multipliedBy($monthsElapsed);
        } else {
            $monthsElapsed = 0;
            $accrued = Decimal::of($this->lifetimeCommissionEarned($membership));
        }

        $pending = $accrued->minus($paidTotal);

        return [
            'pay_type' => $membership->pay_type->value,
            'salary_amount' => (float) Decimal::money($membership->salary_amount),
            'salary_visible_to_staff' => $membership->salary_visible_to_staff,
            'months_elapsed' => $monthsElapsed,
            'accrued' => (float) Decimal::money($accrued),
            'paid_total' => (float) Decimal::money($paidTotal),
            'pending' => (float) Decimal::money($pending->isGreaterThan(0) ? $pending : Decimal::of(0)),
        ];
    }

    private function lifetimeCommissionEarned(BusinessMembership $membership): string
    {
        return (string) Invoice::query()
            ->where('business_id', $membership->business_id)
            ->where('created_by', $membership->user_id)
            ->whereIn('status', DashboardService::LIVE_INVOICE_STATUSES)
            ->sum('commission_amount');
    }

    /** @param array<string, mixed> $data */
    public function pay(Business $business, BusinessMembership $membership, User $actor, array $data): SalaryPayment
    {
        return DB::transaction(function () use ($business, $membership, $actor, $data): SalaryPayment {
            $payment = $membership->salaryPayments()->create([
                'business_id' => $business->id,
                'recorded_by' => $actor->id,
                'payment_date' => $data['payment_date'],
                'amount' => Decimal::money($data['amount']),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->record($actor, $business, 'salary.paid', $payment, null, $payment->toArray());

            return $payment;
        });
    }

    private function monthsElapsed(BusinessMembership $membership): int
    {
        if ($membership->pay_type !== PayType::FixedSalary) {
            return 0;
        }

        $start = $membership->joined_at ? CarbonImmutable::parse($membership->joined_at) : CarbonImmutable::today();
        $now = CarbonImmutable::today();

        if ($start->greaterThan($now)) {
            return 0;
        }

        return max(0, ($now->year - $start->year) * 12 + ($now->month - $start->month) + 1);
    }
}
