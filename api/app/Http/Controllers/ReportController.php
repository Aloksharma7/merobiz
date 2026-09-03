<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\DashboardService;
use App\Support\AuthorizesBusinessActions;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function profitLoss(Request $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'reports.view');
        $range = DateRange::fromRequest($request);
        $metrics = $this->dashboard->metrics($business, $range->start, $range->end);

        return response()->json([
            'period' => $range->toArray(),
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'currency' => $business->currency,
            ],
            'report' => [
                'net_sales' => $metrics['net_sales'],
                'cost_of_sales' => $metrics['cost_of_sales'],
                'gross_profit' => $metrics['gross_profit'],
                'expenses' => $metrics['expenses'],
                'commissions' => $metrics['commissions'],
                'net_profit' => $metrics['net_profit'],
                'tax_collected' => $metrics['tax_collected'],
                'cash_collected' => $metrics['cash_collected'],
                'receivables' => $metrics['receivables'],
            ],
        ]);
    }
}
