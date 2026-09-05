<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryPaymentRequest;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Services\SalaryService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly SalaryService $salary) {}

    public function index(Request $request, Business $business, BusinessMembership $membership): JsonResponse
    {
        $this->requirePermission($request, 'team.manage');
        abort_unless($membership->business_id === $business->id, 404);

        $payments = $membership->salaryPayments()->with('recorder:id,name')->latest('payment_date')->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date->toDateString(),
                'amount' => (float) $payment->amount,
                'method' => $payment->method->value,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'recorded_by' => $payment->recorder?->name,
            ]);

        return response()->json([
            'summary' => $this->salary->summaryFor($membership),
            'payments' => $payments,
        ]);
    }

    public function pay(StoreSalaryPaymentRequest $request, Business $business, BusinessMembership $membership): JsonResponse
    {
        $this->requirePermission($request, 'team.manage');
        abort_unless($membership->business_id === $business->id, 404);

        $this->salary->pay($business, $membership, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Salary payment recorded.',
            'summary' => $this->salary->summaryFor($membership->fresh()),
        ], 201);
    }

    public function mine(Request $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);

        if (! $membership->salary_visible_to_staff) {
            return response()->json(['visible' => false]);
        }

        return response()->json(['visible' => true, ...$this->salary->summaryFor($membership)]);
    }
}
