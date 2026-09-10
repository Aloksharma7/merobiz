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
    public function __construct(private readonly AuditService $audit) {}

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
            if ($amount->isGreaterThan($balance->plus(0.01))) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'You tried to record %s, but only %s is still outstanding on this invoice. Someone may have just recorded another payment — refresh and try again.',
                        number_format((float) $data['amount'], 2),
                        number_format((float) $lockedInvoice->balance_amount, 2),
                    ),
                ]);
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

    /**
     * Corrects a payment recorded with the wrong amount, date, or detail —
     * same reasoning as SalaryService::updatePayment(). Unlike a writer or
     * salary payment, an invoice's paid/balance/status are stored, not
     * derived, so both have to be recalculated here explicitly.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePayment(Business $business, Invoice $invoice, Payment $payment, User $actor, array $data): Payment
    {
        return DB::transaction(function () use ($business, $invoice, $payment, $actor, $data): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($lockedInvoice->business_id === $business->id, 404);
            abort_unless($payment->invoice_id === $lockedInvoice->id, 404);

            if (in_array($lockedInvoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Refunded], true)) {
                throw ValidationException::withMessages(['invoice' => 'Payments cannot be edited on this invoice.']);
            }
            if ($business->isDateClosed($payment->payment_date) || $business->isDateClosed($data['payment_date'])) {
                throw ValidationException::withMessages(['payment_date' => 'This payment belongs to a closed profit period.']);
            }
            if (CarbonImmutable::parse($data['payment_date'])->startOfDay()->lt($lockedInvoice->invoice_date->startOfDay())) {
                throw ValidationException::withMessages(['payment_date' => 'The payment date cannot be before the invoice date.']);
            }

            $oldAmount = Decimal::of($payment->amount);
            $newAmount = Decimal::of($data['amount']);
            // Balance as if this payment never happened — the true ceiling for its new amount.
            $balanceExcludingThis = Decimal::of($lockedInvoice->balance_amount)->plus($oldAmount);
            if ($newAmount->isGreaterThan($balanceExcludingThis->plus(0.01))) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'You tried to record %s, but only %s is outstanding on this invoice (including this payment).',
                        number_format((float) $data['amount'], 2),
                        number_format((float) Decimal::money($balanceExcludingThis), 2),
                    ),
                ]);
            }

            $before = $payment->toArray();
            $payment->update([
                'payment_date' => $data['payment_date'],
                'amount' => Decimal::money($newAmount),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $newPaid = Decimal::of($lockedInvoice->paid_amount)->minus($oldAmount)->plus($newAmount);
            $newBalance = Decimal::of($lockedInvoice->total_amount)->minus($newPaid);
            $status = $newBalance->isZero() ? InvoiceStatus::Paid : ($newPaid->isZero() ? InvoiceStatus::Issued : InvoiceStatus::Partial);

            $lockedInvoice->update([
                'paid_amount' => Decimal::money($newPaid),
                'balance_amount' => Decimal::money($newBalance),
                'status' => $status,
            ]);

            $payment->load(['invoice', 'customer', 'recorder']);
            $this->audit->record($actor, $business, 'payment.updated', $payment, $before, $payment->fresh()->toArray());

            return $payment;
        });
    }

    /**
     * Undoes an accidentally recorded payment — same reasoning as
     * SalaryService::deletePayment(). Reverses the invoice's stored
     * paid/balance/status back to what they'd be without this payment.
     */
    public function deletePayment(Business $business, Invoice $invoice, Payment $payment, User $actor): void
    {
        DB::transaction(function () use ($business, $invoice, $payment, $actor): void {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_unless($lockedInvoice->business_id === $business->id, 404);
            abort_unless($payment->invoice_id === $lockedInvoice->id, 404);

            if (in_array($lockedInvoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Refunded], true)) {
                throw ValidationException::withMessages(['invoice' => 'Payments cannot be undone on this invoice.']);
            }
            if ($business->isDateClosed($payment->payment_date)) {
                throw ValidationException::withMessages(['payment' => 'This payment belongs to a closed profit period.']);
            }

            $newPaid = Decimal::of($lockedInvoice->paid_amount)->minus($payment->amount);
            $newBalance = Decimal::of($lockedInvoice->total_amount)->minus($newPaid);
            $status = $newPaid->isZero() ? InvoiceStatus::Issued : InvoiceStatus::Partial;

            $before = $payment->toArray();
            $payment->delete();

            $lockedInvoice->update([
                'paid_amount' => Decimal::money($newPaid),
                'balance_amount' => Decimal::money($newBalance),
                'status' => $status,
            ]);

            $this->audit->record($actor, $business, 'payment.deleted', $payment, $before, null);
        });
    }
}
