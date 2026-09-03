<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /** @param array<string, mixed> $data */
    public function record(Business $business, Invoice $invoice, User $actor, array $data): Payment
    {
        return DB::transaction(function () use ($business, $invoice, $actor, $data): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($lockedInvoice->business_id === $business->id, 404);

            if ($business->isDateClosed($data['payment_date'])) {
                throw ValidationException::withMessages(['payment_date' => 'This date belongs to a closed profit period.']);
            }
            if (CarbonImmutable::parse($data['payment_date'])->startOfDay()->lt($lockedInvoice->invoice_date->startOfDay())) {
                throw ValidationException::withMessages(['payment_date' => 'The payment date cannot be before the invoice date.']);
            }

            if (in_array($lockedInvoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Refunded], true)) {
                throw ValidationException::withMessages(['invoice' => 'Payments cannot be recorded against this invoice.']);
            }

            $amount = Decimal::of($data['amount']);
            $balance = Decimal::of($lockedInvoice->balance_amount);
            if ($amount->isGreaterThan($balance)) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot be greater than the outstanding balance.']);
            }

            /** @var Business $lockedBusiness */
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->id);
            $paymentNumber = sprintf('%s-PAY-%06d', mb_strtoupper($lockedBusiness->code), $lockedBusiness->payment_next_number);
            $lockedBusiness->increment('payment_next_number');

            $payment = $lockedBusiness->payments()->create([
                'invoice_id' => $lockedInvoice->id,
                'customer_id' => $lockedInvoice->customer_id,
                'recorded_by' => $actor->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $data['payment_date'],
                'amount' => Decimal::money($amount),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaid = Decimal::of($lockedInvoice->paid_amount)->plus($amount);
            $newBalance = Decimal::of($lockedInvoice->total_amount)->minus($newPaid);
            $status = $newBalance->isZero() ? InvoiceStatus::Paid : InvoiceStatus::Partial;

            $lockedInvoice->update([
                'paid_amount' => Decimal::money($newPaid),
                'balance_amount' => Decimal::money($newBalance),
                'status' => $status,
            ]);

            $payment->load(['invoice', 'customer', 'recorder']);
            $this->audit->record($actor, $business, 'payment.recorded', $payment, null, $payment->toArray());

            return $payment;
        });
    }
}
