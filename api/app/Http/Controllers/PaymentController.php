<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Business;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function store(StorePaymentRequest $request, Business $business, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->business_id === $business->id, 404);
        $membership = $this->membership($request);
        $canRecord = $membership->allows('payments.manage');
        $canRecordOwn = $membership->allows('payments.record_own') && $invoice->created_by === $request->user()->id;
        abort_unless($canRecord || $canRecordOwn, 403);

        $payment = $this->payments->record($business, $invoice, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Payment recorded.',
            'payment' => new PaymentResource($payment),
        ], 201);
    }
}
