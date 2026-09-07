<?php

namespace App\Services;

use App\Enums\BusinessCategory;
use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Enums\SalaryEntryType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invoice;
use App\Models\OwnershipPeriod;
use App\Models\ProfitAllocation;
use App\Models\ProfitDistribution;
use App\Models\ProfitWithdrawal;
use App\Models\ProjectProfitApproval;
use App\Models\ProjectRefund;
use App\Models\SalaryPayment;
use App\Models\User;
use App\Models\WriterPayment;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** @var array<int, string> */
    public const LIVE_INVOICE_STATUSES = [
        InvoiceStatus::Issued->value,
        InvoiceStatus::Partial->value,
        InvoiceStatus::Paid->value,
        InvoiceStatus::Overdue->value,
    ];

    /** @return array<string, mixed> */
    public function portfolio(User $user, DateRange $range): array
    {
        $memberships = $user->memberships()
            ->where('active', true)
            ->with('business')
            ->get()
            ->filter(fn (BusinessMembership $membership): bool => $membership->business !== null);

        $previous = $range->previous();
        $businessRows = [];
        $summary = $this->emptyMetrics();
        $summary['attributable_profit'] = 0.0;

        $totalAvailableBalance = 0.0;

        foreach ($memberships as $membership) {
            $business = $membership->business;
            $canViewFinancials = $membership->allows('dashboard.financial');
            $creator = $canViewFinancials ? null : $user;
            $metrics = $this->metrics($business, $range->start, $range->end, $creator);
            $previousMetrics = $this->metrics($business, $previous->start, $previous->end, $creator);
            if (! $canViewFinancials) {
                $metrics = $this->employeeSafeMetrics($metrics);
                $previousMetrics = $this->employeeSafeMetrics($previousMetrics);
            }
            $ownership = $canViewFinancials ? $business->currentOwnershipFor($user, $range->end) : null;
            $attributable = $canViewFinancials
                ? $this->attributableProfit($user, $business, $range->start, $range->end)
                : 0.0;
            $availableBalance = $canViewFinancials ? $this->availableBalance($business) : $this->emptyAvailableBalance();
            $totalAvailableBalance += $availableBalance['available_balance'];
            foreach (array_keys($this->emptyMetrics()) as $key) {
                $summary[$key] += $metrics[$key];
            }
            $summary['attributable_profit'] += $attributable;

            $businessRows[] = [
                'id' => $business->id,
                'name' => $business->name,
                'code' => $business->code,
                'business_type' => $business->business_type,
                'is_installment' => $business->isInstallment(),
                'currency' => $business->currency,
                'status' => $business->status,
                'my_role' => $membership->role->value,
                'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0.0,
                'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0.0,
                'can_view_financials' => $canViewFinancials,
                'metrics' => $metrics + ['attributable_profit' => $attributable],
                'available_balance' => $availableBalance,
                'collected_this_month' => $this->collectedThisMonth($user->id, $business->id),
                'change' => [
                    'net_sales' => $this->percentageChange($metrics['net_sales'], $previousMetrics['net_sales']),
                    'net_profit' => $this->percentageChange($metrics['net_profit'], $previousMetrics['net_profit']),
                ],
            ];
        }

        $financialBusinessIds = $memberships
            ->filter(fn (BusinessMembership $membership): bool => $membership->allows('dashboard.financial'))
            ->pluck('business_id')
            ->all();

        $distributedProfit = (float) ProfitDistribution::query()
            ->where('user_id', $user->id)
            ->whereBetween('distribution_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('amount');

        $outstandingProfit = (float) ProfitAllocation::query()
            ->where('user_id', $user->id)
            ->selectRaw('COALESCE(SUM(allocated_amount - distributed_amount), 0) as outstanding')
            ->value('outstanding');

        $summary = array_map(fn (float $value): float => round($value, 2), $summary);
        $summary['average_invoice'] = $summary['invoice_count'] > 0
            ? round($summary['invoiced_total'] / $summary['invoice_count'], 2)
            : 0.0;
        $summary['distributed_profit'] = round($distributedProfit, 2);
        $summary['outstanding_profit'] = round($outstandingProfit, 2);

        $currencies = $memberships
            ->pluck('business.currency')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $portfolioMode = $memberships->isEmpty()
            ? 'owner'
            : ($memberships->contains(fn (BusinessMembership $membership): bool => $membership->role === BusinessRole::Owner)
                ? 'owner'
                : ($memberships->contains(fn (BusinessMembership $membership): bool => $membership->role === BusinessRole::Admin) ? 'admin' : 'employee'));

        if ($portfolioMode === 'employee') {
            $summary['distributed_profit'] = 0.0;
            $summary['outstanding_profit'] = 0.0;
        }

        $today = CarbonImmutable::now();

        return [
            'period' => $range->toArray(),
            'mode' => $portfolioMode,
            'can_create_business' => $memberships->isEmpty() || $portfolioMode === 'owner',
            'reporting_currency' => $user->preferred_currency ?: ($currencies[0] ?? 'NPR'),
            'currencies' => $currencies,
            'mixed_currencies' => count($currencies) > 1,
            'summary' => $summary,
            'total_available_balance' => round($totalAvailableBalance, 2),
            'month_to_date' => $this->portfolioQuickWindow($memberships, $user, $today->startOfMonth(), $today, $portfolioMode),
            'profit_collected_this_month' => $this->collectedThisMonth($user->id),
            'businesses' => $businessRows,
            'trend' => $this->portfolioTrend($user, $memberships, $range->end),
            'top_sellers' => $this->topSellers($financialBusinessIds, $range->start, $range->end),
            'recent_activity' => $this->recentActivity($financialBusinessIds),
        ];
    }

    /** @param Collection<int, BusinessMembership> $memberships */
    private function portfolioQuickWindow(Collection $memberships, User $user, CarbonInterface $start, CarbonInterface $end, string $mode): array
    {
        $sales = 0.0;
        $profit = 0.0;

        foreach ($memberships as $membership) {
            $business = $membership->business;
            $canViewFinancials = $membership->allows('dashboard.financial');
            $creator = $canViewFinancials ? null : $user;
            $metrics = $this->metrics($business, $start, $end, $creator);
            $sales += $metrics['net_sales'];

            if ($mode === 'owner') {
                $profit += $canViewFinancials ? $this->attributableProfit($user, $business, $start, $end) : 0.0;
            } elseif ($canViewFinancials) {
                $profit += $metrics['net_profit'];
            }
        }

        return ['sales' => round($sales, 2), 'profit' => round($profit, 2)];
    }

    private function collectedThisMonth(int $userId, ?int $businessId = null): float
    {
        $today = CarbonImmutable::now();

        return (float) ProfitWithdrawal::query()
            ->where('user_id', $userId)
            ->when($businessId, fn ($query) => $query->where('business_id', $businessId))
            ->whereBetween('withdrawn_on', [$today->startOfMonth()->toDateString(), $today->toDateString()])
            ->sum('amount');
    }

    private function lifetimeWithdrawn(int $userId, int $businessId): float
    {
        return (float) ProfitWithdrawal::query()
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->sum('amount');
    }

    /** @return array<string, mixed> */
    public function business(Business $business, User $user, BusinessMembership $membership, DateRange $range): array
    {
        $canViewFinancials = $membership->allows('dashboard.financial');
        $creator = $canViewFinancials ? null : $user;
        $metrics = $this->metrics($business, $range->start, $range->end, $creator);
        $previousMetrics = $this->metrics($business, $range->previous()->start, $range->previous()->end, $creator);
        if (! $canViewFinancials) {
            $metrics = $this->employeeSafeMetrics($metrics);
            $previousMetrics = $this->employeeSafeMetrics($previousMetrics);
        }
        // Always their own record regardless of dashboard.financial — same reasoning as
        // $attributable below.
        $ownership = $business->currentOwnershipFor($user, $range->end);
        // Always computed, even for someone without dashboard.financial — this is only
        // ever their OWN profit-share cut, never the business's overall figures, so it's
        // as safe to show as their own sales commission used to be.
        $attributable = $this->attributableProfit($user, $business, $range->start, $range->end);
        // Lifetime (not date-range scoped), same reasoning as available_balance below —
        // "how much of what I've ever earned have I already taken out" doesn't make
        // sense measured against just the selected period.
        $lifetimeEarned = $this->attributableProfit($user, $business, CarbonImmutable::parse($business->created_at), CarbonImmutable::now());
        $lifetimeWithdrawn = $this->lifetimeWithdrawn($user->id, $business->id);

        $today = CarbonImmutable::now();
        $monthMetrics = $this->metrics($business, $today->startOfMonth(), $today, $creator);
        if (! $canViewFinancials) {
            $monthMetrics = $this->employeeSafeMetrics($monthMetrics);
        }

        return [
            'period' => $range->toArray(),
            'mode' => $canViewFinancials ? 'financial' : 'personal',
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'code' => $business->code,
                'currency' => $business->currency,
                'business_type' => $business->business_type,
                'category' => $business->category->value,
                'is_installment' => $business->isInstallment(),
                'my_role' => $membership->role->value,
                'full_control' => $membership->full_control,
                'is_founder' => $user->id === $business->owner_id,
                'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0.0,
                'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0.0,
                'dashboard_settings' => $this->dashboardSettings($business),
            ],
            'owners' => $this->activeOwners($business, $canViewFinancials),
            'month_to_date' => ['sales' => $monthMetrics['net_sales'], 'profit' => $monthMetrics['net_profit']],
            'profit_collected_this_month' => $canViewFinancials && $membership->role === BusinessRole::Owner
                ? $this->collectedThisMonth($user->id, $business->id)
                : 0.0,
            'permissions' => [
                'can_view_financials' => $canViewFinancials,
                'can_manage_sales' => $membership->allows('sales.manage') || $membership->allows('sales.create'),
                'can_manage_payments' => $membership->allows('payments.manage'),
                'can_manage_expenses' => $membership->allows('expenses.manage'),
                'can_approve_expenses' => $membership->allows('expenses.approve'),
                'can_manage_customers' => $membership->allows('customers.manage'),
                'can_manage_products' => $membership->allows('products.manage'),
                'can_manage_team' => $membership->allows('team.manage'),
                'can_view_reports' => $membership->allows('reports.view'),
                'can_update_business' => $membership->allows('business.update'),
                'can_manage_ownership' => $membership->full_control,
            ],
            'summary' => $metrics + [
                'attributable_profit' => round($attributable, 2),
                'lifetime_profit_earned' => round($lifetimeEarned, 2),
                'lifetime_profit_withdrawn' => round($lifetimeWithdrawn, 2),
                'profit_available_to_withdraw' => round($lifetimeEarned - $lifetimeWithdrawn, 2),
                'net_sales_change' => $this->percentageChange($metrics['net_sales'], $previousMetrics['net_sales']),
                'net_profit_change' => $this->percentageChange($metrics['net_profit'], $previousMetrics['net_profit']),
            ],
            // Not date-range scoped on purpose — a balance is a point-in-time snapshot
            // of what the business actually has, not a period total. It only ever
            // changes because of a new transaction, never because the date filter moved.
            'available_balance' => $canViewFinancials ? $this->availableBalance($business) : $this->emptyAvailableBalance(),
            'trend' => $this->businessTrend($business, $range->end, $creator),
            'top_sellers' => $canViewFinancials
                ? $this->topSellers([$business->id], $range->start, $range->end)
                : $this->topSellers([$business->id], $range->start, $range->end, $user->id),
            'top_products' => $this->topProducts($business, $range->start, $range->end, $creator),
            'expense_categories' => $canViewFinancials
                ? $this->expenseCategories($business, $range->start, $range->end)
                : [],
            'recent_invoices' => $this->recentInvoices($business, $creator),
        ];
    }

    /**
     * @return array{net_sales: float, invoiced_total: float, tax_collected: float, cost_of_sales: float, gross_profit: float, expenses: float, commissions: float, payroll_cost: float, writer_cost: float, project_profit: float, refunds: float, net_profit: float, cash_collected: float, receivables: float, invoice_count: float, average_invoice: float}
     */
    public function metrics(Business $business, CarbonInterface $start, CarbonInterface $end, ?User $creator = null): array
    {
        $invoices = Invoice::query()
            ->where('business_id', $business->id)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', self::LIVE_INVOICE_STATUSES);

        if ($creator) {
            $invoices->where('created_by', $creator->id);
        }

        $isInstallment = $business->category === BusinessCategory::Installment;

        // One aggregate query instead of eight separate SUM/COUNT round trips —
        // this runs up to a dozen times per dashboard load (current period, prior
        // period, month-to-date, and once per point on the 6-month trend chart).
        $aggregate = (clone $invoices)->selectRaw(
            'COALESCE(SUM(subtotal), 0) as subtotal, '.
            'COALESCE(SUM(discount_amount), 0) as discount_amount, '.
            'COALESCE(SUM(tax_amount), 0) as tax_amount, '.
            'COALESCE(SUM(total_amount), 0) as total_amount, '.
            'COALESCE(SUM(cost_amount), 0) as cost_amount, '.
            'COALESCE(SUM(commission_amount), 0) as commission_amount, '.
            'COALESCE(SUM(balance_amount), 0) as balance_amount, '.
            'COUNT(*) as invoice_count'
        )->first();

        $subtotal = (float) $aggregate->subtotal;
        $discounts = (float) $aggregate->discount_amount;
        $tax = (float) $aggregate->tax_amount;
        $invoicedTotal = (float) $aggregate->total_amount;
        // Installment/project businesses don't recognize invoice cost and margin
        // automatically — profit only counts once approved on the project.
        $cost = $isInstallment ? 0.0 : (float) $aggregate->cost_amount;
        $commissions = $isInstallment ? 0.0 : (float) $aggregate->commission_amount;
        $receivables = (float) $aggregate->balance_amount;
        $invoiceCount = (float) $aggregate->invoice_count;

        $payments = $business->payments()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);

        if ($creator) {
            $payments->whereHas('invoice', fn (Builder $query) => $query->where('created_by', $creator->id));
        }

        // affects_profit=false means this expense is cash catching up to a cost
        // already recognized as COGS on an invoice item — it still reduces available
        // balance (see availableBalance() below, which does not filter on this flag)
        // but must not deduct from profit a second time here. That exemption is
        // capped at $cost (the real COGS recognized across every sale in this same
        // period, combined) — someone can mark more expenses "already priced in"
        // than that, but only up to $cost is genuinely already accounted for; any
        // amount claimed beyond it isn't actually priced into any sale, so it falls
        // back to reducing profit like a normal expense.
        // One query for both sums (not two) — metrics() runs up to ten times per
        // dashboard load, so an extra round trip here adds up fast.
        $expenseTotals = $creator ? null : $business->expenses()
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN affects_profit THEN amount ELSE 0 END), 0) as normal_total, '.
                'COALESCE(SUM(CASE WHEN affects_profit THEN 0 ELSE amount END), 0) as exempt_claimed')
            ->first();
        $expenses = $creator ? 0.0 : (float) $expenseTotals->normal_total;
        $exemptClaimed = $creator ? 0.0 : (float) $expenseTotals->exempt_claimed;
        $expenses += max(0, $exemptClaimed - $cost);

        $projectProfit = $creator ? 0.0 : $this->approvedProjectProfit($business, $start, $end);
        // A refund is money physically handed back for an aborted project — it
        // reduces what the business actually collected, same as an expense reduces profit.
        $refunds = $creator ? 0.0 : $this->refundedAmount($business, $start, $end);
        // `commissions` (below, from the invoice's estimated commission_amount) is
        // informational only — an estimate of what a commission-based employee is
        // owed, shown on their payroll page. It is NOT subtracted here, because the
        // estimate frequently doesn't match what's actually paid out (a 0% rate, an
        // ad-hoc bonus, a rate changed after the sale, etc). The only thing that
        // reduces profit is real money actually leaving the business: every payroll
        // payment/advance (either pay type) and any loan that gets written off.
        // A loan that hasn't been written off is excluded — it's still expected back.
        $payrollCost = $creator ? 0.0 : $this->payrollCost($business, $start, $end);
        // A writer payment is a real cost the same way payroll is — actually paying a
        // writer for a file must reduce profit, not just adjust the project's own
        // "still due to writer" tracker. Only installment businesses can ever have
        // writer payments — skip the query entirely everywhere else, the same reason
        // payrollCost() is a single query rather than two: metrics() runs up to ten
        // times per dashboard load.
        $writerCost = ($creator || ! $isInstallment) ? 0.0 : $this->writerCost($business, $start, $end);
        $cashCollected = (float) $payments->sum('amount') - $refunds;

        $netSales = $subtotal - $discounts;
        $grossProfit = $isInstallment ? 0.0 : ($netSales - $cost);
        $netProfit = $grossProfit - $expenses - $payrollCost - $writerCost - $refunds + $projectProfit;

        return [
            'net_sales' => round($netSales, 2),
            'invoiced_total' => round($invoicedTotal, 2),
            'tax_collected' => round($tax, 2),
            'cost_of_sales' => round($cost, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses' => round($expenses, 2),
            'commissions' => round($commissions, 2),
            'payroll_cost' => round($payrollCost, 2),
            'writer_cost' => round($writerCost, 2),
            'project_profit' => round($projectProfit, 2),
            'refunds' => round($refunds, 2),
            'net_profit' => round($netProfit, 2),
            'cash_collected' => round($cashCollected, 2),
            'receivables' => round($receivables, 2),
            'invoice_count' => $invoiceCount,
            'average_invoice' => $invoiceCount > 0 ? round($invoicedTotal / $invoiceCount, 2) : 0.0,
        ];
    }

    /**
     * The business's actual cash position — everything that's ever come in minus
     * everything that's ever gone out, for any reason. Deliberately not date-range
     * scoped: a balance is what the business has right now, not a period total.
     * This is a cash view, not a profit view, so it differs from net_profit in two
     * ways: a loan given to staff reduces it immediately (the cash is gone, even
     * though it's not a cost — see payrollCost()), and an owner's profit withdrawal
     * reduces it too (real cash out, even though it's a distribution, not a cost).
     *
     * @return array{available_balance: float, collected: float, refunded: float, expenses_paid: float, payroll_paid: float, writer_paid: float, owner_withdrawn: float}
     */
    public function availableBalance(Business $business): array
    {
        $isInstallment = $business->category === BusinessCategory::Installment;

        $collected = (float) $business->payments()->sum('amount');
        $refunded = $isInstallment
            ? (float) ProjectRefund::query()->where('business_id', $business->id)->sum('amount')
            : 0.0;
        $expensesPaid = (float) $business->expenses()->where('status', 'approved')->sum('amount');
        // A loan counts here (real cash left the business the moment it was given),
        // unlike payrollCost() where a loan is excluded until written off. A
        // write-off is the opposite: no new cash moves, so it's excluded here.
        $payrollPaid = (float) SalaryPayment::query()
            ->where('business_id', $business->id)
            ->whereIn('entry_type', [SalaryEntryType::Payment->value, SalaryEntryType::Advance->value, SalaryEntryType::Loan->value])
            ->sum('amount');
        $writerPaid = $isInstallment
            ? (float) WriterPayment::query()->where('business_id', $business->id)->sum('amount')
            : 0.0;
        $ownerWithdrawn = (float) ProfitWithdrawal::query()->where('business_id', $business->id)->sum('amount');

        $balance = $collected - $refunded - $expensesPaid - $payrollPaid - $writerPaid - $ownerWithdrawn;

        return [
            'available_balance' => round($balance, 2),
            'collected' => round($collected, 2),
            'refunded' => round($refunded, 2),
            'expenses_paid' => round($expensesPaid, 2),
            'payroll_paid' => round($payrollPaid, 2),
            'writer_paid' => round($writerPaid, 2),
            'owner_withdrawn' => round($ownerWithdrawn, 2),
        ];
    }

    /** @return array{available_balance: float, collected: float, refunded: float, expenses_paid: float, payroll_paid: float, writer_paid: float, owner_withdrawn: float} */
    private function emptyAvailableBalance(): array
    {
        return [
            'available_balance' => 0.0,
            'collected' => 0.0,
            'refunded' => 0.0,
            'expenses_paid' => 0.0,
            'payroll_paid' => 0.0,
            'writer_paid' => 0.0,
            'owner_withdrawn' => 0.0,
        ];
    }

    private function payrollCost(Business $business, CarbonInterface $start, CarbonInterface $end): float
    {
        // Every pay type counts the same way now — a payment or advance is real
        // money out regardless of whether the recipient is salaried or commission
        // based, and a write-off is a real loss once a loan is forgiven. A loan
        // that's still outstanding is deliberately excluded (not yet a cost).
        return (float) SalaryPayment::query()
            ->where('business_id', $business->id)
            ->whereIn('entry_type', [SalaryEntryType::Payment->value, SalaryEntryType::Advance->value, SalaryEntryType::WriteOff->value])
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    private function writerCost(Business $business, CarbonInterface $start, CarbonInterface $end): float
    {
        return (float) WriterPayment::query()
            ->where('business_id', $business->id)
            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    private function approvedProjectProfit(Business $business, CarbonInterface $start, CarbonInterface $end): float
    {
        if ($business->category !== BusinessCategory::Installment) {
            return 0.0;
        }

        return (float) ProjectProfitApproval::query()
            ->where('business_id', $business->id)
            ->whereBetween('approved_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    private function refundedAmount(Business $business, CarbonInterface $start, CarbonInterface $end): float
    {
        if ($business->category !== BusinessCategory::Installment) {
            return 0.0;
        }

        return (float) ProjectRefund::query()
            ->where('business_id', $business->id)
            ->whereBetween('refunded_on', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
    }

    /**
     * $excludePayroll exists only for computing what a profit-share pay-type team
     * member is owed (see SalaryService::lifetimeProfitShareEarned()) — paying
     * someone their profit share is itself a payroll cost, which would otherwise
     * shrink net_profit and, in turn, shrink their own accrued entitlement the
     * moment they're paid, turning an exact, correct payment into an apparent
     * overpayment. Excluding payroll here breaks that circular dependency by
     * measuring the pool before anyone's payout is subtracted from it — the same
     * way a real profit-sharing pool is decided before it's paid out of. The
     * dashboard's own attributable profit (the true bottom-line figure) always
     * calls this with the default false, so it still reflects every real cost.
     */
    public function attributableProfit(User $user, Business $business, CarbonInterface $start, CarbonInterface $end, bool $excludePayroll = false): float
    {
        $periods = OwnershipPeriod::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function (Builder $query) use ($start): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start->toDateString());
            })
            ->orderBy('effective_from')
            ->get();

        $total = 0.0;
        foreach ($periods as $period) {
            $segmentStart = CarbonImmutable::parse($period->effective_from)->max(CarbonImmutable::instance($start));
            $segmentEnd = $period->effective_to
                ? CarbonImmutable::parse($period->effective_to)->min(CarbonImmutable::instance($end))
                : CarbonImmutable::instance($end);

            if ($segmentStart->greaterThan($segmentEnd)) {
                continue;
            }

            $metrics = $this->metrics($business, $segmentStart, $segmentEnd);
            $baseProfit = $excludePayroll ? $metrics['net_profit'] + $metrics['payroll_cost'] : $metrics['net_profit'];
            $total += $baseProfit * ((float) $period->profit_share_percent / 100);
        }

        return round($total, 2);
    }

    /** @var array<string, bool> */
    private const DEFAULT_DASHBOARD_SETTINGS = [
        'show_overview_cards' => true,
        'show_profit_breakdown' => true,
        'show_quick_actions' => true,
        'show_performance_trend' => true,
        'show_top_products' => true,
        'show_recent_invoices' => true,
        'show_expense_mix' => true,
    ];

    /** @return array<string, bool> */
    private function dashboardSettings(Business $business): array
    {
        $stored = (array) data_get($business->settings, 'dashboard', []);

        return array_merge(self::DEFAULT_DASHBOARD_SETTINGS, array_intersect_key($stored, self::DEFAULT_DASHBOARD_SETTINGS));
    }

    /** @return array<int, array<string, mixed>> */
    private function activeOwners(Business $business, bool $includeShares): array
    {
        $today = CarbonImmutable::now()->toDateString();

        $periods = $business->ownerships()
            ->whereDate('effective_from', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->with('user:id,name')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('user_id');

        $titles = $business->memberships()->where('role', BusinessRole::Owner->value)->pluck('title', 'user_id');

        return $periods->map(fn (OwnershipPeriod $period): array => [
            'user_id' => $period->user_id,
            'name' => $period->user->name,
            'initials' => $period->user->initials,
            'title' => $titles->get($period->user_id),
            'ownership_percent' => $includeShares ? (float) $period->ownership_percent : null,
            'profit_share_percent' => $includeShares ? (float) $period->profit_share_percent : null,
        ])->values()->all();
    }

    /** @return array<string, float> */
    private function emptyMetrics(): array
    {
        return [
            'net_sales' => 0.0,
            'invoiced_total' => 0.0,
            'tax_collected' => 0.0,
            'cost_of_sales' => 0.0,
            'gross_profit' => 0.0,
            'expenses' => 0.0,
            'commissions' => 0.0,
            'payroll_cost' => 0.0,
            'writer_cost' => 0.0,
            'project_profit' => 0.0,
            'refunds' => 0.0,
            'net_profit' => 0.0,
            'cash_collected' => 0.0,
            'receivables' => 0.0,
            'invoice_count' => 0.0,
            'average_invoice' => 0.0,
        ];
    }

    /** @param array<string, float> $metrics @return array<string, float> */
    private function employeeSafeMetrics(array $metrics): array
    {
        // Employees need sales/collection performance, not internal margin or business profit.
        $metrics['cost_of_sales'] = 0.0;
        $metrics['gross_profit'] = 0.0;
        $metrics['expenses'] = 0.0;
        $metrics['payroll_cost'] = 0.0;
        $metrics['writer_cost'] = 0.0;
        $metrics['project_profit'] = 0.0;
        $metrics['net_profit'] = 0.0;

        return $metrics;
    }

    private function percentageChange(float $current, float $previous): float
    {
        if (abs($previous) < 0.01) {
            return abs($current) < 0.01 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * @param  Collection<int, BusinessMembership>  $memberships
     * @return array<int, array<string, mixed>>
     */
    private function portfolioTrend(User $user, Collection $memberships, CarbonInterface $anchor): array
    {
        $points = [];
        $cursor = CarbonImmutable::instance($anchor)->startOfMonth()->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $monthStart = $cursor->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth()->min(CarbonImmutable::instance($anchor)->endOfDay());
            $netSales = 0.0;
            $netProfit = 0.0;
            $attributable = 0.0;

            foreach ($memberships as $membership) {
                $business = $membership->business;
                $canViewFinancials = $membership->allows('dashboard.financial');
                $metrics = $this->metrics($business, $monthStart, $monthEnd, $canViewFinancials ? null : $user);
                $netSales += $metrics['net_sales'];
                if ($canViewFinancials) {
                    $netProfit += $metrics['net_profit'];
                }
                if ($canViewFinancials) {
                    $attributable += $this->attributableProfit($user, $business, $monthStart, $monthEnd);
                }
            }

            $points[] = [
                'label' => $monthStart->format('M'),
                'month' => $monthStart->format('Y-m'),
                'net_sales' => round($netSales, 2),
                'net_profit' => round($netProfit, 2),
                'attributable_profit' => round($attributable, 2),
            ];
        }

        return $points;
    }

    /** @return array<int, array<string, mixed>> */
    private function businessTrend(Business $business, CarbonInterface $anchor, ?User $creator): array
    {
        $points = [];
        $cursor = CarbonImmutable::instance($anchor)->startOfMonth()->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $monthStart = $cursor->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth()->min(CarbonImmutable::instance($anchor)->endOfDay());
            $metrics = $this->metrics($business, $monthStart, $monthEnd, $creator);
            $points[] = [
                'label' => $monthStart->format('M'),
                'month' => $monthStart->format('Y-m'),
                'net_sales' => $metrics['net_sales'],
                'net_profit' => $creator ? 0.0 : $metrics['net_profit'],
                'cash_collected' => $metrics['cash_collected'],
            ];
        }

        return $points;
    }

    /**
     * @param  array<int, int>  $businessIds
     * @return array<int, array<string, mixed>>
     */
    private function topSellers(array $businessIds, CarbonInterface $start, CarbonInterface $end, ?int $onlyUserId = null): array
    {
        if ($businessIds === []) {
            return [];
        }

        $query = Invoice::query()
            ->select('created_by')
            ->selectRaw('SUM(subtotal - discount_amount) as sales')
            ->selectRaw('SUM(commission_amount) as commission')
            ->selectRaw('COUNT(*) as invoice_count')
            ->whereIn('business_id', $businessIds)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', self::LIVE_INVOICE_STATUSES)
            ->groupBy('created_by')
            ->orderByDesc('sales')
            ->limit(8);

        if ($onlyUserId) {
            $query->where('created_by', $onlyUserId);
        }

        $rows = $query->get();
        $users = User::query()->whereIn('id', $rows->pluck('created_by'))->get()->keyBy('id');

        return $rows->map(function (Invoice $row) use ($users): array {
            $seller = $users->get($row->created_by);

            return [
                'user_id' => $row->created_by,
                'name' => $seller?->name ?? 'Unknown',
                'initials' => $seller?->initials ?? '—',
                'sales' => round((float) $row->getAttribute('sales'), 2),
                'commission' => round((float) $row->getAttribute('commission'), 2),
                'invoice_count' => (int) $row->getAttribute('invoice_count'),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function topProducts(Business $business, CarbonInterface $start, CarbonInterface $end, ?User $creator): array
    {
        $query = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.business_id', $business->id)
            ->whereBetween('invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('invoices.status', self::LIVE_INVOICE_STATUSES)
            ->selectRaw('COALESCE(products.name, invoice_items.description) as name')
            ->selectRaw('SUM(invoice_items.quantity) as quantity')
            ->selectRaw('SUM(invoice_items.line_total - invoice_items.tax_amount) as sales')
            ->groupBy('invoice_items.product_id', 'products.name', 'invoice_items.description')
            ->orderByDesc('sales')
            ->limit(5);

        if ($creator) {
            $query->where('invoices.created_by', $creator->id);
        }

        return $query->get()->map(fn (object $row): array => [
            'name' => (string) $row->name,
            'quantity' => round((float) $row->quantity, 3),
            'sales' => round((float) $row->sales, 2),
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function expenseCategories(Business $business, CarbonInterface $start, CarbonInterface $end): array
    {
        return $business->expenses()
            ->select('category')
            ->selectRaw('SUM(amount) as total')
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'category' => $row->category,
                'total' => round((float) $row->getAttribute('total'), 2),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function recentInvoices(Business $business, ?User $creator): array
    {
        $query = $business->invoices()
            ->with(['customer:id,name', 'creator:id,name'])
            ->latest('invoice_date')
            ->latest('id')
            ->limit(8);

        if ($creator) {
            $query->where('created_by', $creator->id);
        }

        return $query->get()->map(fn (Invoice $invoice): array => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'customer_name' => $invoice->customer_name ?: $invoice->customer?->name ?: 'Walk-in customer',
            'seller_name' => $invoice->creator->name,
            'status' => $invoice->status->value,
            'total_amount' => (float) $invoice->total_amount,
            'balance_amount' => (float) $invoice->balance_amount,
        ])->all();
    }

    /**
     * @param  array<int, int>  $businessIds
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(array $businessIds): array
    {
        if ($businessIds === []) {
            return [];
        }

        return AuditLog::query()
            ->with(['business:id,name,code', 'user:id,name'])
            ->whereIn('business_id', $businessIds)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'label' => str($log->action)->replace('.', ' ')->headline()->toString(),
                'business_name' => $log->business?->name,
                'business_code' => $log->business?->code,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all();
    }
}
