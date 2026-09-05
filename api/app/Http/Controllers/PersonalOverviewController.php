<?php

namespace App\Http\Controllers;

use App\Models\ProfitWithdrawal;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $range = DateRange::fromRequest($request);

        $profitQuery = ProfitWithdrawal::query()
            ->where('user_id', $user->id)
            ->whereBetween('withdrawn_on', [$range->start->toDateString(), $range->end->toDateString()]);

        $businessProfitReceived = (float) (clone $profitQuery)->sum('amount');

        $otherIncome = (float) $user->personalIncomeEntries()
            ->whereBetween('entry_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('amount');

        $totalExpenses = (float) $user->personalExpenses()
            ->whereBetween('expense_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('amount');

        $totalIncome = $businessProfitReceived + $otherIncome;

        $recentProfit = (clone $profitQuery)
            ->with('business:id,name,currency')
            ->latest('withdrawn_on')
            ->limit(6)
            ->get()
            ->map(fn (ProfitWithdrawal $row): array => [
                'id' => $row->id,
                'business_name' => $row->business?->name,
                'currency' => $row->business?->currency,
                'amount' => (float) $row->amount,
                'withdrawn_on' => $row->withdrawn_on->toDateString(),
            ]);

        $recentIncome = $user->personalIncomeEntries()
            ->with('source:id,name,type')
            ->whereBetween('entry_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->latest('entry_date')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'source_name' => $row->source?->name,
                'amount' => (float) $row->amount,
                'entry_date' => $row->entry_date->toDateString(),
            ]);

        $recentExpenses = $user->personalExpenses()
            ->whereBetween('expense_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->latest('expense_date')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'category' => $row->category,
                'vendor' => $row->vendor,
                'amount' => (float) $row->amount,
                'expense_date' => $row->expense_date->toDateString(),
            ]);

        return response()->json([
            'period' => $range->toArray(),
            'reporting_currency' => $user->preferred_currency ?: 'NPR',
            'summary' => [
                'business_profit_received' => round($businessProfitReceived, 2),
                'other_income' => round($otherIncome, 2),
                'total_income' => round($totalIncome, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_balance' => round($totalIncome - $totalExpenses, 2),
            ],
            'recent_profit' => $recentProfit,
            'recent_income' => $recentIncome,
            'recent_expenses' => $recentExpenses,
        ]);
    }
}
