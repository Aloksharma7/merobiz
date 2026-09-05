<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryPaymentRequest;
use App\Http\Requests\WriteOffLoanRequest;
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

        return response()->json([
            'summary' => $this->salary->summaryFor($membership),
            'payments' => $this->mapPayments($membership),
        ]);
    }

    public function pay(StoreSalaryPaymentRequest $request, Business $business, BusinessMembership $membership): JsonResponse
    {
        $this->requirePermission($request, 'team.manage');
        abort_unless($membership->business_id === $business->id, 404);

        $this->salary->pay($business, $membership, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Payment recorded.',
            'summary' => $this->salary->summaryFor($membership->fresh()),
        ], 201);
    }

    public function writeOffLoan(WriteOffLoanRequest $request, Business $business, BusinessMembership $membership): JsonResponse
    {
        $this->requirePermission($request, 'team.manage');
        abort_unless($membership->business_id === $business->id, 404);

        $this->salary->writeOffLoan($business, $membership, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Loan settled.',
            'summary' => $this->salary->summaryFor($membership->fresh()),
        ], 201);
    }

    public function mine(Request $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        $summary = $this->salary->summaryFor($membership);

        if (! $membership->salary_visible_to_staff) {
            // The visibility toggle is about hiding pay-rate detail, not about
            // hiding money the employee was given as a loan and still owes back —
            // that stays visible regardless so they know why it happened.
            return response()->json(['visible' => false, 'outstanding_loan' => $summary['outstanding_loan']]);
        }

        return response()->json(['visible' => true, ...$summary, 'payments' => $this->mapPayments($membership)]);
    }

    /** @return array<int, array<string, mixed>> */
    private function mapPayments(BusinessMembership $membership): array
    {
        return $membership->salaryPayments()->with('recorder:id,name')->latest('payment_date')->latest('id')->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_date' => $payment->payment_date->toDateString(),
                'amount' => (float) $payment->amount,
                'entry_type' => $payment->entry_type->value,
                'method' => $payment->method->value,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'recorded_by' => $payment->recorder?->name,
            ])->all();
    }
}
