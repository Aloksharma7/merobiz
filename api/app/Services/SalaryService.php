<?php

namespace App\Services;

use App\Enums\PayType;
use App\Enums\SalaryEntryType;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invoice;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Support\Decimal;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly DashboardService $dashboard,
    ) {}

    /** @return array<string, mixed> */
    public function summaryFor(BusinessMembership $membership): array
    {
        $payrollPaid = Decimal::of($membership->salaryPayments()
            ->whereIn('entry_type', [SalaryEntryType::Payment->value, SalaryEntryType::Advance->value])
            ->sum('amount'));

        if ($membership->pay_type === PayType::FixedSalary) {
            $monthsElapsed = $this->monthsElapsed($membership);
            $accrued = Decimal::of($membership->salary_amount)->multipliedBy($monthsElapsed);
        } elseif ($membership->pay_type === PayType::ProfitShare) {
            $monthsElapsed = 0;
            $accrued = Decimal::of($this->lifetimeProfitShareEarned($membership));
        } else {
            // Dormant: commission is disabled business-wide, but existing rows
            // (or a future re-enable) still resolve through the original formula.
            $monthsElapsed = 0;
            $accrued = Decimal::of($this->lifetimeCommissionEarned($membership));
        }

        // Not floored at zero — an employee who was advanced more than they've
        // earned owes it back out of what they earn next, and that should be
        // visible rather than silently hidden as "nothing owed".
        $pending = $accrued->minus($payrollPaid);

        $loanGiven = Decimal::of($membership->salaryPayments()->where('entry_type', SalaryEntryType::Loan->value)->sum('amount'));
        $loanWrittenOff = Decimal::of($membership->salaryPayments()->where('entry_type', SalaryEntryType::WriteOff->value)->sum('amount'));
        $outstandingLoan = $loanGiven->minus($loanWrittenOff);

        return [
            'pay_type' => $membership->pay_type->value,
            'salary_amount' => (float) Decimal::money($membership->salary_amount),
            'salary_visible_to_staff' => $membership->salary_visible_to_staff,
            'months_elapsed' => $monthsElapsed,
            'accrued' => (float) Decimal::money($accrued),
            'paid_total' => (float) Decimal::money($payrollPaid),
            'pending' => (float) Decimal::money($pending),
            'outstanding_loan' => (float) Decimal::money($outstandingLoan->isGreaterThan(0) ? $outstandingLoan : BigDecimal::zero()),
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

    private function lifetimeProfitShareEarned(BusinessMembership $membership): string
    {
        $start = CarbonImmutable::parse($membership->business->created_at)->startOfDay();
        $end = CarbonImmutable::now();

        return (string) $this->dashboard->attributableProfit($membership->user, $membership->business, $start, $end);
    }

    /** @param array<string, mixed> $data */
    public function pay(Business $business, BusinessMembership $membership, User $actor, array $data): SalaryPayment
    {
        return DB::transaction(function () use ($business, $membership, $actor, $data): SalaryPayment {
            $entryType = SalaryEntryType::from($data['entry_type'] ?? SalaryEntryType::Payment->value);

            $payment = $membership->salaryPayments()->create([
                'business_id' => $business->id,
                'recorded_by' => $actor->id,
                'payment_date' => $data['payment_date'],
                'amount' => Decimal::money($data['amount']),
                'entry_type' => $entryType,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->record($actor, $business, 'salary.'.$entryType->value, $payment, null, $payment->toArray());

            return $payment;
        });
    }

    /** @param array<string, mixed> $data */
    public function writeOffLoan(Business $business, BusinessMembership $membership, User $actor, array $data): SalaryPayment
    {
        return DB::transaction(function () use ($business, $membership, $actor, $data): SalaryPayment {
            /** @var BusinessMembership $locked */
            $locked = BusinessMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $outstanding = Decimal::of($this->summaryFor($locked)['outstanding_loan']);
            $amount = Decimal::of($data['amount']);

            if ($amount->isGreaterThan($outstanding->plus(0.01))) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Only %s is still outstanding on this loan.', Decimal::money($outstanding)),
                ]);
            }

            $writeOff = $locked->salaryPayments()->create([
                'business_id' => $business->id,
                'recorded_by' => $actor->id,
                'payment_date' => now()->toDateString(),
                'amount' => Decimal::money($amount),
                'entry_type' => SalaryEntryType::WriteOff,
                'method' => 'other',
                'reference' => null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->record($actor, $business, 'salary.write_off', $writeOff, null, $writeOff->toArray());

            return $writeOff;
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
