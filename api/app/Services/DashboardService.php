<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invoice;
use App\Models\OwnershipPeriod;
use App\Models\ProfitAllocation;
use App\Models\ProfitDistribution;
use App\Models\User;
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
            foreach (array_keys($this->emptyMetrics()) as $key) {
                $summary[$key] += $metrics[$key];
            }
            $summary['attributable_profit'] += $attributable;

            $businessRows[] = [
                'id' => $business->id,
                'name' => $business->name,
                'code' => $business->code,
                'business_type' => $business->business_type,
                'currency' => $business->currency,
                'status' => $business->status,
                'my_role' => $membership->role->value,
                'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0.0,
                'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0.0,
                'can_view_financials' => $canViewFinancials,
                'metrics' => $metrics + ['attributable_profit' => $attributable],
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

        return [
            'period' => $range->toArray(),
            'mode' => $portfolioMode,
            'can_create_business' => $memberships->isEmpty() || $portfolioMode === 'owner',
            'reporting_currency' => $user->preferred_currency ?: ($currencies[0] ?? 'NPR'),
            'currencies' => $currencies,
            'mixed_currencies' => count($currencies) > 1,
            'summary' => $summary,
            'businesses' => $businessRows,
            'trend' => $this->portfolioTrend($user, $memberships, $range->end),
            'top_sellers' => $this->topSellers($financialBusinessIds, $range->start, $range->end),
            'recent_activity' => $this->recentActivity($financialBusinessIds),
        ];
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
        $ownership = $canViewFinancials ? $business->currentOwnershipFor($user, $range->end) : null;
        $attributable = $canViewFinancials
            ? $this->attributableProfit($user, $business, $range->start, $range->end)
            : 0.0;

        return [
            'period' => $range->toArray(),
            'mode' => $canViewFinancials ? 'financial' : 'personal',
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'code' => $business->code,
                'currency' => $business->currency,
                'business_type' => $business->business_type,
                'my_role' => $membership->role->value,
                'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0.0,
                'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0.0,
            ],
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
                'can_manage_ownership' => $membership->role->value === 'owner',
            ],
            'summary' => $metrics + [
                'attributable_profit' => round($attributable, 2),
                'net_sales_change' => $this->percentageChange($metrics['net_sales'], $previousMetrics['net_sales']),
                'net_profit_change' => $this->percentageChange($metrics['net_profit'], $previousMetrics['net_profit']),
            ],
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
     * @return array{net_sales: float, invoiced_total: float, tax_collected: float, cost_of_sales: float, gross_profit: float, expenses: float, commissions: float, net_profit: float, cash_collected: float, receivables: float, invoice_count: float, average_invoice: float}
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

        $subtotal = (float) (clone $invoices)->sum('subtotal');
        $discounts = (float) (clone $invoices)->sum('discount_amount');
        $tax = (float) (clone $invoices)->sum('tax_amount');
        $invoicedTotal = (float) (clone $invoices)->sum('total_amount');
        $cost = (float) (clone $invoices)->sum('cost_amount');
        $commissions = (float) (clone $invoices)->sum('commission_amount');
        $receivables = (float) (clone $invoices)->sum('balance_amount');
        $invoiceCount = (float) (clone $invoices)->count();

        $payments = $business->payments()
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);

        if ($creator) {
            $payments->whereHas('invoice', fn (Builder $query) => $query->where('created_by', $creator->id));
        }

        $cashCollected = (float) $payments->sum('amount');
        $expenses = $creator ? 0.0 : (float) $business->expenses()
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $netSales = $subtotal - $discounts;
        $grossProfit = $netSales - $cost;
        $netProfit = $grossProfit - $expenses - $commissions;

        return [
            'net_sales' => round($netSales, 2),
            'invoiced_total' => round($invoicedTotal, 2),
            'tax_collected' => round($tax, 2),
            'cost_of_sales' => round($cost, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses' => round($expenses, 2),
            'commissions' => round($commissions, 2),
            'net_profit' => round($netProfit, 2),
            'cash_collected' => round($cashCollected, 2),
            'receivables' => round($receivables, 2),
            'invoice_count' => $invoiceCount,
            'average_invoice' => $invoiceCount > 0 ? round($invoicedTotal / $invoiceCount, 2) : 0.0,
        ];
    }

    public function attributableProfit(User $user, Business $business, CarbonInterface $start, CarbonInterface $end): float
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
            $total += $metrics['net_profit'] * ((float) $period->profit_share_percent / 100);
        }

        return round($total, 2);
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
     * @param Collection<int, BusinessMembership> $memberships
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
     * @param array<int, int> $businessIds
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
     * @param array<int, int> $businessIds
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
