<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\PanInvoiceSequence;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Support\Decimal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(Business $business, User $creator, array $data): Invoice
    {
        return DB::transaction(function () use ($business, $creator, $data): Invoice {
            /** @var Business $lockedBusiness */
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->id);

            if ($lockedBusiness->isDateClosed($data['invoice_date'])) {
                throw ValidationException::withMessages(['invoice_date' => 'This date belongs to a closed profit period.']);
            }

            $customerId = Arr::get($data, 'customer_id');
            $customer = $customerId ? $lockedBusiness->customers()->whereKey($customerId)->first() : null;
            if ($customerId && ! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'The selected customer does not belong to this business.']);
            }

            $projectId = Arr::get($data, 'project_id');
            /** @var Project|null $project */
            $project = $projectId ? $lockedBusiness->projects()->whereKey($projectId)->first() : null;
            if ($lockedBusiness->isInstallment() && ! $project) {
                throw ValidationException::withMessages(['project_id' => 'Select a project for this sale — sales in this business are made against a project.']);
            }
            if ($projectId && ! $project) {
                throw ValidationException::withMessages(['project_id' => 'The selected project does not belong to this business.']);
            }

            // A project always has a tracked customer — inherit it so payments recorded
            // against the project's invoices show up in that customer's payment history.
            if (! $customerId && $project?->customer_id) {
                $customerId = $project->customer_id;
            }

            // Customer details are snapshotted on the invoice. Staff may type a name
            // directly for one-off billing without creating a permanent customer first.
            // If a saved customer or project is selected, its details are used as defaults.
            $customerName = trim((string) (Arr::get($data, 'customer_name') ?: $customer?->name ?: $project?->client_name ?: 'Walk-in Customer'));
            $customerSnapshot = [
                'customer_name' => $customerName,
                'customer_phone' => Arr::get($data, 'customer_phone') ?: $customer?->phone ?: $project?->client_phone,
                'customer_email' => Arr::get($data, 'customer_email') ?: $customer?->email ?: $project?->client_email,
                'customer_address' => Arr::get($data, 'customer_address') ?: $customer?->address,
                'customer_pan_number' => Arr::get($data, 'customer_pan_number') ?: $customer?->pan_number,
            ];

            $status = InvoiceStatus::from(Arr::get($data, 'status', InvoiceStatus::Issued->value));
            $membership = $lockedBusiness->memberships()
                ->where('user_id', $creator->id)
                ->where('active', true)
                ->firstOrFail();
            $canManageCatalogue = $membership->allows('products.manage');
            $preparedItems = $this->prepareItems($lockedBusiness, $data['items'], $canManageCatalogue);
            $totals = $this->calculateTotals($preparedItems, Arr::get($data, 'discount_amount', 0));

            if ($project) {
                $due = $project->dueAmount();
                if (Decimal::of($totals['total_amount'])->isGreaterThan(Decimal::of($due)->plus(0.01))) {
                    throw ValidationException::withMessages([
                        'amount' => sprintf(
                            'You tried to record %s, but only %s is still due on this project. Someone may have just recorded another payment — refresh and try again.',
                            number_format((float) $totals['total_amount'], 2),
                            number_format($due, 2),
                        ),
                    ]);
                }
            }

            $netSales = Decimal::of($totals['subtotal'])->minus(Decimal::of($totals['discount_amount']));
            // Commission is earned on what the sale actually made the business, not on
            // the revenue collected — a sale at or below cost earns no commission at all.
            $grossProfit = $netSales->minus(Decimal::of($totals['cost_amount']));
            $commissionableProfit = $grossProfit->isNegative() ? BigDecimal::zero() : $grossProfit;
            $commission = $commissionableProfit
                ->multipliedBy(Decimal::of($membership->commission_rate))
                ->dividedBy(100, 2, RoundingMode::HalfUp);

            $invoiceNumber = $this->nextInvoiceNumber($lockedBusiness);

            $invoice = $lockedBusiness->invoices()->create([
                'customer_id' => $customerId,
                'project_id' => $project?->id,
                ...$customerSnapshot,
                'created_by' => $creator->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $data['invoice_date'],
                'due_date' => Arr::get($data, 'due_date'),
                'status' => $status,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'cost_amount' => $totals['cost_amount'],
                'commission_amount' => Decimal::money($commission),
                'paid_amount' => '0.00',
                'balance_amount' => $totals['total_amount'],
                'notes' => Arr::get($data, 'notes'),
                'finalized_at' => $status === InvoiceStatus::Issued ? now() : null,
            ]);

            foreach ($preparedItems as $index => $item) {
                $calculated = $totals['items'][$index];
                $invoice->items()->create([
                    'product_id' => $item['product']?->id,
                    'description' => $item['description'],
                    'quantity' => Decimal::quantity($item['quantity']),
                    'unit_price' => Decimal::money($item['unit_price']),
                    'cost_price' => Decimal::money($item['cost_price']),
                    'discount_amount' => $calculated['discount_amount'],
                    'tax_rate' => Decimal::money($item['tax_rate']),
                    'tax_amount' => $calculated['tax_amount'],
                    'line_total' => $calculated['line_total'],
                ]);

                if ($status === InvoiceStatus::Issued) {
                    $this->decrementInventory($item['product'], $item['quantity']);
                }
            }

            if (! empty($data['installments'])) {
                if (! $lockedBusiness->hasFeature('installments') && ! $lockedBusiness->isInstallment()) {
                    throw ValidationException::withMessages(['installments' => 'Installment plans are not enabled for this business.']);
                }
                $this->createInstallments($invoice, $data['installments'], $totals['total_amount']);
            }

            $invoice->load(['customer', 'project', 'creator', 'items.product', 'payments', 'installments']);
            $this->audit->record($creator, $lockedBusiness, 'invoice.created', $invoice, null, $invoice->toArray());

            return $invoice;
        });
    }

    private function nextInvoiceNumber(Business $lockedBusiness): string
    {
        $panNumber = trim((string) $lockedBusiness->pan_number);
        $sharesPan = $panNumber !== '' && Business::query()
            ->where('pan_number', $panNumber)
            ->where('id', '!=', $lockedBusiness->id)
            ->exists();

        if ($sharesPan) {
            /** @var PanInvoiceSequence $sequence */
            $sequence = PanInvoiceSequence::query()->lockForUpdate()->firstOrCreate(
                ['pan_number' => $panNumber],
                ['next_number' => 1],
            );
            $number = (string) $sequence->next_number;
            $sequence->increment('next_number');

            return $number;
        }

        $number = (string) $lockedBusiness->invoice_next_number;
        $lockedBusiness->increment('invoice_next_number');

        return $number;
    }

    /** @param array<int, array<string, mixed>> $installments */
    private function createInstallments(Invoice $invoice, array $installments, string $totalAmount): void
    {
        $sum = BigDecimal::zero();
        $rows = [];
        $invoiceDate = $invoice->invoice_date->toDateString();

        foreach ($installments as $index => $installment) {
            if (CarbonImmutable::parse($installment['due_date'])->toDateString() < $invoiceDate) {
                throw ValidationException::withMessages(['installments' => 'Each installment must be due on or after the invoice date.']);
            }

            $amount = Decimal::of($installment['amount']);
            $sum = $sum->plus($amount);
            $rows[] = [
                'sequence' => $index + 1,
                'due_date' => $installment['due_date'],
                'amount' => Decimal::money($amount),
                'notes' => $installment['notes'] ?? null,
            ];
        }

        if (! $sum->isEqualTo(Decimal::of($totalAmount))) {
            throw ValidationException::withMessages(['installments' => 'The installment amounts must add up to the invoice total.']);
        }

        $invoice->installments()->createMany($rows);
    }

    public function issue(Business $business, Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($business, $invoice, $actor): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->with('items.product')->lockForUpdate()->findOrFail($invoice->id);
            $this->assertBelongsToBusiness($business, $lockedInvoice);

            if ($lockedInvoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be issued.']);
            }
            if ($business->isDateClosed($lockedInvoice->invoice_date)) {
                throw ValidationException::withMessages(['invoice' => 'This invoice belongs to a closed profit period.']);
            }

            foreach ($lockedInvoice->items as $item) {
                $this->decrementInventory($item->product, Decimal::of($item->quantity));
            }

            $before = $lockedInvoice->toArray();
            $lockedInvoice->update([
                'status' => InvoiceStatus::Issued,
                'finalized_at' => now(),
            ]);

            $this->audit->record($actor, $business, 'invoice.issued', $lockedInvoice, $before, $lockedInvoice->fresh()->toArray());

            return $lockedInvoice->fresh(['customer', 'creator', 'items.product', 'payments']);
        });
    }

    public function cancel(Business $business, Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($business, $invoice, $actor): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->with('items.product')->lockForUpdate()->findOrFail($invoice->id);
            $this->assertBelongsToBusiness($business, $lockedInvoice);

            if ($business->isDateClosed($lockedInvoice->invoice_date)) {
                throw ValidationException::withMessages(['invoice' => 'This invoice belongs to a closed profit period.']);
            }

            if (Decimal::of($lockedInvoice->paid_amount)->isGreaterThan(0)) {
                throw ValidationException::withMessages(['invoice' => 'An invoice with payments cannot be cancelled. Record a refund workflow instead.']);
            }

            if (in_array($lockedInvoice->status, [InvoiceStatus::Cancelled, InvoiceStatus::Refunded], true)) {
                throw ValidationException::withMessages(['invoice' => 'This invoice is already closed.']);
            }

            if ($lockedInvoice->status !== InvoiceStatus::Draft) {
                $this->restoreInventory($lockedInvoice);
            }

            $before = $lockedInvoice->toArray();
            $lockedInvoice->update([
                'status' => InvoiceStatus::Cancelled,
                'balance_amount' => '0.00',
            ]);
            $this->audit->record($actor, $business, 'invoice.cancelled', $lockedInvoice, $before, $lockedInvoice->fresh()->toArray());

            return $lockedInvoice->fresh(['customer', 'creator', 'items.product', 'payments']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareItems(Business $business, array $items, bool $canManageCatalogue): array
    {
        return collect($items)->map(function (array $item) use ($business, $canManageCatalogue): array {
            $product = null;
            if ($productId = Arr::get($item, 'product_id')) {
                $product = $business->products()->whereKey($productId)->first();
                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'One or more selected products do not belong to this business.']);
                }
            }

            if (! $product && ! $canManageCatalogue && ! $business->isInstallment()) {
                throw ValidationException::withMessages([
                    'items' => 'Employees must select a product or service from the business catalogue.',
                ]);
            }

            return [
                'product' => $product,
                'description' => (string) $item['description'],
                'quantity' => Decimal::of($item['quantity']),
                'unit_price' => Decimal::of($item['unit_price']),
                // Product cost is authoritative for employee sales — they can't see or
                // set it from the browser. Whoever can manage the catalogue may override
                // it per line (e.g. this particular unit cost more than usual), same as
                // the tax_rate override just below.
                'cost_price' => $product && ! $canManageCatalogue
                    ? Decimal::of($product->cost_price)
                    : Decimal::of(Arr::get($item, 'unit_cost', $product?->cost_price ?? 0)),
                'line_discount' => Decimal::of(Arr::get($item, 'discount_amount', 0)),
                // Employees cannot alter a catalogue item's tax rule from the browser.
                'tax_rate' => $product && ! $canManageCatalogue
                    ? Decimal::of($product->tax_rate)
                    : Decimal::of(Arr::get($item, 'tax_rate', $product?->tax_rate ?? $business->default_tax_rate)),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function calculateTotals(array $items, mixed $invoiceDiscount): array
    {
        $subtotal = BigDecimal::zero();
        $taxableBeforeGlobal = BigDecimal::zero();
        $costAmount = BigDecimal::zero();
        $normalized = [];

        foreach ($items as $item) {
            /** @var BigDecimal $quantity */
            $quantity = $item['quantity'];
            /** @var BigDecimal $unitPrice */
            $unitPrice = $item['unit_price'];
            /** @var BigDecimal $lineDiscount */
            $lineDiscount = $item['line_discount'];

            $base = $quantity->multipliedBy($unitPrice);
            if ($lineDiscount->isGreaterThan($base)) {
                throw ValidationException::withMessages(['items' => 'An item discount cannot be greater than its line value.']);
            }

            $taxable = $base->minus($lineDiscount);
            $subtotal = $subtotal->plus($base);
            $taxableBeforeGlobal = $taxableBeforeGlobal->plus($taxable);
            $costAmount = $costAmount->plus($quantity->multipliedBy($item['cost_price']));
            $normalized[] = $item + ['base' => $base, 'taxable' => $taxable];
        }

        $globalDiscount = Decimal::of($invoiceDiscount);
        if ($globalDiscount->isGreaterThan($taxableBeforeGlobal)) {
            throw ValidationException::withMessages(['discount_amount' => 'The invoice discount cannot exceed the invoice value.']);
        }

        $remainingDiscount = $globalDiscount;
        $taxAmount = BigDecimal::zero();
        $totalAmount = BigDecimal::zero();
        $itemResults = [];
        $lastIndex = count($normalized) - 1;

        foreach ($normalized as $index => $item) {
            $allocated = BigDecimal::zero();
            if (! $globalDiscount->isZero() && ! $taxableBeforeGlobal->isZero()) {
                $allocated = $index === $lastIndex
                    ? $remainingDiscount
                    : $globalDiscount
                        ->multipliedBy($item['taxable'])
                        ->dividedBy($taxableBeforeGlobal, 2, RoundingMode::HalfUp);

                if ($allocated->isGreaterThan($remainingDiscount)) {
                    $allocated = $remainingDiscount;
                }
            }

            $remainingDiscount = $remainingDiscount->minus($allocated);
            $discount = $item['line_discount']->plus($allocated);
            $taxable = $item['base']->minus($discount);
            $tax = $taxable
                ->multipliedBy($item['tax_rate'])
                ->dividedBy(100, 2, RoundingMode::HalfUp);
            $lineTotal = $taxable->plus($tax);

            $taxAmount = $taxAmount->plus($tax);
            $totalAmount = $totalAmount->plus($lineTotal);
            $itemResults[] = [
                'discount_amount' => Decimal::money($discount),
                'tax_amount' => Decimal::money($tax),
                'line_total' => Decimal::money($lineTotal),
            ];
        }

        $discountTotal = $subtotal->minus($totalAmount->minus($taxAmount));

        return [
            'subtotal' => Decimal::money($subtotal),
            'discount_amount' => Decimal::money($discountTotal),
            'tax_amount' => Decimal::money($taxAmount),
            'total_amount' => Decimal::money($totalAmount),
            'cost_amount' => Decimal::money($costAmount),
            'items' => $itemResults,
        ];
    }

    private function decrementInventory(?Product $product, BigDecimal $quantity): void
    {
        if (! $product?->track_inventory) {
            return;
        }

        /** @var Product $lockedProduct */
        $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
        if (Decimal::of($lockedProduct->stock_quantity)->isLessThan($quantity)) {
            throw ValidationException::withMessages([
                'items' => sprintf('Not enough stock for %s. Available: %s.', $lockedProduct->name, $lockedProduct->stock_quantity),
            ]);
        }

        $lockedProduct->update([
            'stock_quantity' => Decimal::quantity(Decimal::of($lockedProduct->stock_quantity)->minus($quantity)),
        ]);
    }

    private function restoreInventory(Invoice $invoice): void
    {
        foreach ($invoice->items as $item) {
            if (! $item->product?->track_inventory) {
                continue;
            }

            $lockedProduct = $item->product()->lockForUpdate()->first();
            $lockedProduct?->update([
                'stock_quantity' => Decimal::quantity(Decimal::of($lockedProduct->stock_quantity)->plus(Decimal::of($item->quantity))),
            ]);
        }
    }

    public function delete(Business $business, Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($business, $invoice, $actor): void {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->with(['items.product', 'payments'])->lockForUpdate()->findOrFail($invoice->id);
            $this->assertBelongsToBusiness($business, $lockedInvoice);

            if ($business->isDateClosed($lockedInvoice->invoice_date)) {
                throw ValidationException::withMessages(['invoice' => 'This invoice belongs to a closed profit period.']);
            }

            if (! in_array($lockedInvoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Refunded], true)) {
                $this->restoreInventory($lockedInvoice);
            }

            $before = $lockedInvoice->toArray();
            // Payments use nullOnDelete on their invoice_id so they survive an invoice
            // delete by default — that would leave their amounts still counted in
            // business-wide cash-collected totals for a sale that no longer exists.
            $lockedInvoice->payments()->delete();
            $lockedInvoice->delete();

            $this->audit->record($actor, $business, 'invoice.deleted', $invoice, $before, null);
        });
    }

    private function assertBelongsToBusiness(Business $business, Invoice $invoice): void
    {
        abort_unless($invoice->business_id === $business->id, 404);
    }
}
