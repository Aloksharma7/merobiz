<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly PaymentService $payments) {}

    public function store(StorePaymentRequest $request, Business $business, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->business_id === $business->id, 404);
        $this->assertCanManagePayment($request, $invoice);

        $payment = $this->payments->record($business, $invoice, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Payment recorded.',
            'payment' => new PaymentResource($payment),
        ], 201);
    }

    public function update(StorePaymentRequest $request, Business $business, Invoice $invoice, Payment $payment): JsonResponse
    {
        abort_unless($invoice->business_id === $business->id, 404);
        abort_unless($payment->invoice_id === $invoice->id, 404);
        $this->assertCanManagePayment($request, $invoice);

        $updated = $this->payments->updatePayment($business, $invoice, $payment, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Payment updated.',
            'payment' => new PaymentResource($updated),
        ]);
    }

    public function destroy(Request $request, Business $business, Invoice $invoice, Payment $payment): JsonResponse
    {
        abort_unless($invoice->business_id === $business->id, 404);
        abort_unless($payment->invoice_id === $invoice->id, 404);
        $this->assertCanDeletePayment($request, $business, $invoice);

        $this->payments->deletePayment($business, $invoice, $payment, $request->user());

        return response()->json(['message' => 'Payment deleted.']);
    }

    private function assertCanManagePayment(Request $request, Invoice $invoice): void
    {
        $membership = $this->membership($request);
        $canRecord = $membership->allows('payments.manage');
        $canRecordOwn = $membership->allows('payments.record_own') && $invoice->created_by === $request->user()->id;
        abort_unless($canRecord || $canRecordOwn, 403);
    }

    /**
     * Undoing a payment (unlike recording or editing one) also opens up to the
     * whole team in a thesis/installment business, the same as deleting the
     * sale itself does — any staff who can record a payment can undo any of
     * them there. A standard business keeps the tighter own-invoice-only rule.
     */
    private function assertCanDeletePayment(Request $request, Business $business, Invoice $invoice): void
    {
        $membership = $this->membership($request);
        $canManage = $membership->allows('payments.manage')
            || ($business->isInstallment() && $membership->allows('payments.record_own'));
        $canDeleteOwn = $membership->allows('payments.record_own') && $invoice->created_by === $request->user()->id;
        abort_unless($canManage || $canDeleteOwn, 403);
    }
}
