<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Invoice;
use App\Support\AuthorizesBusinessActions;
use App\Support\AuthorizesInvoiceAccess;
use App\Support\Decimal;
use App\Support\InstallmentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceInstallmentController extends Controller
{
    use AuthorizesBusinessActions;
    use AuthorizesInvoiceAccess;

    public function index(Request $request, Business $business, Invoice $invoice): JsonResponse
    {
        $this->assertVisible($request, $business, $invoice);
        $invoice->loadMissing('installments');

        return response()->json(['data' => InstallmentSchedule::annotate($invoice->installments, $invoice->paid_amount)]);
    }

    public function store(Request $request, Business $business, Invoice $invoice): JsonResponse
    {
        $this->assertEditable($request, $business, $invoice);
        abort_unless($invoice->business_id === $business->id, 404);
        abort_unless($business->hasFeature('installments'), 422, 'Installment plans are not enabled for this business.');

        if ($business->isDateClosed($invoice->invoice_date)) {
            throw ValidationException::withMessages(['installments' => 'This invoice belongs to a closed profit period.']);
        }

        $data = $request->validate([
            'installments' => ['required', 'array', 'min:2'],
            'installments.*.due_date' => ['required', 'date', 'after_or_equal:'.$invoice->invoice_date->toDateString()],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            'installments.*.notes' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($invoice, $data): void {
            $sum = Decimal::of(0);
            $rows = [];

            foreach ($data['installments'] as $index => $installment) {
                $amount = Decimal::of($installment['amount']);
                $sum = $sum->plus($amount);
                $rows[] = [
                    'sequence' => $index + 1,
                    'due_date' => CarbonImmutable::parse($installment['due_date'])->toDateString(),
                    'amount' => Decimal::money($amount),
                    'notes' => $installment['notes'] ?? null,
                ];
            }

            if (! $sum->isEqualTo(Decimal::of($invoice->total_amount))) {
                throw ValidationException::withMessages(['installments' => 'The installment amounts must add up to the invoice total.']);
            }

            $invoice->installments()->delete();
            $invoice->installments()->createMany($rows);
        });

        $invoice->load('installments');

        return response()->json([
            'message' => 'Payment plan updated.',
            'data' => InstallmentSchedule::annotate($invoice->installments, $invoice->paid_amount),
        ]);
    }
}
