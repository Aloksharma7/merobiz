<?php

namespace Database\Seeders;

use App\Enums\BusinessRole;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ProfitClosingService;
use App\Services\ProfitDistributionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $owner = User::query()->firstOrCreate(
                ['email' => 'owner@merobiz.test'],
                ['name' => 'Alok Sharma', 'phone' => '9800000001', 'password' => 'password', 'preferred_currency' => 'NPR'],
            );
            $sales = User::query()->firstOrCreate(
                ['email' => 'employee@merobiz.test'],
                ['name' => 'Sanjay Yadav', 'phone' => '9800000002', 'password' => 'password', 'preferred_currency' => 'NPR'],
            );
            $admin = User::query()->firstOrCreate(
                ['email' => 'admin@merobiz.test'],
                ['name' => 'Nisha Karki', 'phone' => '9800000003', 'password' => 'password', 'preferred_currency' => 'NPR'],
            );
            $partner = User::query()->firstOrCreate(
                ['email' => 'partner@merobiz.test'],
                ['name' => 'Demo Partner', 'phone' => '9800000004', 'password' => 'password', 'preferred_currency' => 'NPR'],
            );

            if (Business::query()->exists()) {
                return;
            }

            $definitions = [
                [
                    'business' => [
                        'name' => 'Aimers AI', 'slug' => 'aimers-ai', 'code' => 'AAI',
                        'business_type' => 'digital_subscription', 'invoice_prefix' => 'AAI',
                        'email' => 'billing@aimersai.test', 'phone' => '9801001001',
                        'address' => 'Kathmandu, Nepal', 'default_tax_rate' => 0,
                    ],
                    'owner_share' => 40,
                    'products' => [
                        ['sku' => 'AI-MONTH', 'name' => 'AI Pro — Monthly', 'type' => 'digital_subscription', 'unit' => 'account', 'sale_price' => 2400, 'cost_price' => 1600, 'tax_rate' => 0, 'metadata' => ['renewal_days' => 30]],
                        ['sku' => 'AI-YEAR', 'name' => 'AI Team — Annual', 'type' => 'digital_subscription', 'unit' => 'seat', 'sale_price' => 18500, 'cost_price' => 13200, 'tax_rate' => 0, 'metadata' => ['renewal_days' => 365]],
                        ['sku' => 'AI-SETUP', 'name' => 'AI Workspace Setup', 'type' => 'service', 'unit' => 'project', 'sale_price' => 6500, 'cost_price' => 1400, 'tax_rate' => 0],
                    ],
                    'customers' => ['Everest Education Hub', 'Himalayan Media House', 'Kathmandu Skills Lab', 'Lotus Consultancy'],
                    'expense_categories' => ['Software & hosting', 'Advertising', 'Internet'],
                ],
                [
                    'business' => [
                        'name' => 'TechChamp Software', 'slug' => 'techchamp-software', 'code' => 'TCS',
                        'business_type' => 'service', 'invoice_prefix' => 'TCS',
                        'email' => 'accounts@techchamp.test', 'phone' => '9801001002',
                        'address' => 'Lalitpur, Nepal', 'default_tax_rate' => 13,
                    ],
                    'owner_share' => 65,
                    'products' => [
                        ['sku' => 'WEB-BASIC', 'name' => 'Business Website', 'type' => 'service', 'unit' => 'project', 'sale_price' => 48000, 'cost_price' => 18000, 'tax_rate' => 13],
                        ['sku' => 'APP-MVP', 'name' => 'Mobile App MVP', 'type' => 'service', 'unit' => 'project', 'sale_price' => 125000, 'cost_price' => 58000, 'tax_rate' => 13],
                        ['sku' => 'SUPPORT', 'name' => 'Monthly Support', 'type' => 'service', 'unit' => 'month', 'sale_price' => 15000, 'cost_price' => 4500, 'tax_rate' => 13],
                    ],
                    'customers' => ['Bara Agro Traders', 'Nepal Learning Network', 'Janaki Retail', 'Sagarmatha Logistics'],
                    'expense_categories' => ['Developer costs', 'Cloud services', 'Office rent'],
                ],
                [
                    'business' => [
                        'name' => 'Enlighten Research', 'slug' => 'enlighten-research', 'code' => 'ERC',
                        'business_type' => 'service', 'invoice_prefix' => 'ERC',
                        'email' => 'finance@enlighten.test', 'phone' => '9801001003',
                        'address' => 'Kathmandu, Nepal', 'default_tax_rate' => 0,
                    ],
                    'owner_share' => 100,
                    'products' => [
                        ['sku' => 'PROPOSAL', 'name' => 'Research Proposal Support', 'type' => 'service', 'unit' => 'project', 'sale_price' => 22000, 'cost_price' => 7000, 'tax_rate' => 0],
                        ['sku' => 'ANALYSIS', 'name' => 'Data Analysis Package', 'type' => 'service', 'unit' => 'project', 'sale_price' => 18000, 'cost_price' => 5000, 'tax_rate' => 0],
                        ['sku' => 'FULL-STUDY', 'name' => 'Full Research Support', 'type' => 'service', 'unit' => 'project', 'sale_price' => 55000, 'cost_price' => 21000, 'tax_rate' => 0],
                    ],
                    'customers' => ['Sushant Basnet', 'Kiran Bhusal', 'Aakash Research Group', 'Sarita Tamang'],
                    'expense_categories' => ['Research assistants', 'Printing & binding', 'Office supplies'],
                ],
            ];

            foreach ($definitions as $businessIndex => $definition) {
                $business = Business::query()->create([
                    ...$definition['business'],
                    'owner_id' => $owner->id,
                    'currency' => 'NPR',
                    'pan_number' => null,
                    'vat_number' => null,
                    'status' => 'active',
                    'settings' => ['country' => 'NP', 'number_format' => 'en-NP', 'fiscal_year' => 'gregorian'],
                ]);

                $business->memberships()->createMany([
                    ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'title' => 'Portfolio Owner', 'commission_rate' => 0, 'active' => true, 'joined_at' => now()->subYear()->toDateString()],
                    ['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'title' => 'Business Admin', 'commission_rate' => 0, 'active' => true, 'joined_at' => now()->subMonths(8)->toDateString()],
                ]);

                // The demo employee is intentionally assigned only to TechChamp Software.
                // This demonstrates that an employee cannot see or access other businesses.
                if ($business->code === 'TCS') {
                    $business->memberships()->create([
                        'user_id' => $sales->id,
                        'role' => BusinessRole::Employee,
                        'title' => 'Sales Employee',
                        'commission_rate' => 5,
                        'active' => true,
                        'joined_at' => now()->subMonths(9)->toDateString(),
                    ]);
                }

                $business->ownerships()->create([
                    'user_id' => $owner->id,
                    'ownership_percent' => $definition['owner_share'],
                    'profit_share_percent' => $definition['owner_share'],
                    'effective_from' => now()->subYears(2)->startOfYear()->toDateString(),
                    'notes' => 'Demo opening ownership',
                ]);

                if ($definition['owner_share'] < 100) {
                    $business->memberships()->create([
                        'user_id' => $partner->id,
                        'role' => BusinessRole::Owner,
                        'title' => 'Business Partner',
                        'commission_rate' => 0,
                        'active' => true,
                        'joined_at' => now()->subYear()->toDateString(),
                    ]);
                    $business->ownerships()->create([
                        'user_id' => $partner->id,
                        'ownership_percent' => 100 - $definition['owner_share'],
                        'profit_share_percent' => 100 - $definition['owner_share'],
                        'effective_from' => now()->subYears(2)->startOfYear()->toDateString(),
                        'notes' => 'Demo partner ownership',
                    ]);
                }

                $products = collect($definition['products'])->map(fn (array $product) => $business->products()->create([
                    ...$product,
                    'track_inventory' => false,
                    'stock_quantity' => 0,
                    'reorder_level' => 0,
                    'active' => true,
                ]));

                $customers = collect($definition['customers'])->map(fn (string $name, int $index) => $business->customers()->create([
                    'name' => $name,
                    'phone' => '98'.str_pad((string) ($businessIndex * 100000 + $index + 12000000), 8, '0', STR_PAD_LEFT),
                    'email' => 'customer'.($businessIndex + 1).($index + 1).'@example.test',
                    'address' => ['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Bara'][$index % 4].', Nepal',
                    'opening_balance' => 0,
                    'active' => true,
                ]));

                $transactionEmployee = $business->code === 'TCS' ? $sales : $owner;
                $this->seedTransactions($business, $owner, $admin, $transactionEmployee, $products->all(), $customers->all(), $definition['expense_categories'], $businessIndex);
            }

            $lastMonthStart = CarbonImmutable::now()->subMonth()->startOfMonth();
            $lastMonthEnd = CarbonImmutable::now()->subMonth()->endOfMonth();
            $closing = app(ProfitClosingService::class);
            $distribution = app(ProfitDistributionService::class);

            foreach (Business::query()->get() as $business) {
                $period = $closing->close($business, $owner, [
                    'start_date' => $lastMonthStart->toDateString(),
                    'end_date' => $lastMonthEnd->toDateString(),
                    'notes' => 'Demo month-end close',
                ]);
                $ownerAllocation = $period->allocations->firstWhere('user_id', $owner->id);
                if ($ownerAllocation && (float) $ownerAllocation->allocated_amount > 0) {
                    $distribution->record($business, $owner, [
                        'profit_allocation_id' => $ownerAllocation->id,
                        'distribution_date' => CarbonImmutable::now()->toDateString(),
                        'amount' => round((float) $ownerAllocation->allocated_amount * 0.6, 2),
                        'method' => PaymentMethod::BankTransfer->value,
                        'reference' => 'DEMO-DIST-'.$business->code,
                        'notes' => '60% of the owner allocation paid; remainder stays payable.',
                    ]);
                }
            }
        });
    }

    /**
     * @param array<int, \App\Models\Product> $products
     * @param array<int, \App\Models\Customer> $customers
     * @param array<int, string> $expenseCategories
     */
    private function seedTransactions(Business $business, User $owner, User $admin, User $sales, array $products, array $customers, array $expenseCategories, int $businessIndex): void
    {
        $invoiceService = app(InvoiceService::class);
        $paymentService = app(PaymentService::class);
        $anchor = CarbonImmutable::now()->startOfMonth();

        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $month = $anchor->subMonths($monthOffset);
            $currentMonth = $month->isSameMonth(CarbonImmutable::now());
            $days = $currentMonth ? [1] : [5, 14, 24];

            foreach ($days as $dayIndex => $day) {
                $date = $month->day(min($day, $month->daysInMonth));
                $creator = ($dayIndex + $businessIndex + $monthOffset) % 3 === 0 ? $owner : $sales;
                $product = $products[($dayIndex + $monthOffset + $businessIndex) % count($products)];
                $customer = $customers[($dayIndex + $monthOffset) % count($customers)];
                $quantity = $product->sale_price < 10000 ? 1 + (($monthOffset + $dayIndex) % 3) : 1;
                $unitPrice = (float) $product->sale_price * (1 + (($monthOffset + $businessIndex) % 3) * 0.03);
                $discount = ($dayIndex === 1 && ! $currentMonth) ? round($unitPrice * 0.04, 2) : 0;

                $invoice = $invoiceService->create($business, $creator, [
                    'customer_id' => $customer->id,
                    'invoice_date' => $date->toDateString(),
                    'due_date' => $date->addDays(10)->toDateString(),
                    'status' => InvoiceStatus::Issued->value,
                    'discount_amount' => $discount,
                    'notes' => $product->type->value === 'digital_subscription'
                        ? 'Digital access and renewal details confirmed with customer.'
                        : 'Thank you for your business.',
                    'items' => [[
                        'product_id' => $product->id,
                        'description' => $product->name,
                        'quantity' => $quantity,
                        'unit_price' => round($unitPrice, 2),
                        'unit_cost' => $product->cost_price,
                        'discount_amount' => 0,
                        'tax_rate' => $product->tax_rate,
                    ]],
                ]);

                if ($creator->id !== $owner->id && ! $currentMonth) {
                }

                $paymentRatio = (($dayIndex + $monthOffset) % 4 === 0 && ! $currentMonth) ? 0.55 : 1;
                $paymentAmount = round((float) $invoice->total_amount * $paymentRatio, 2);
                if ($paymentAmount > 0) {
                    $paymentDate = $date
                        ->addDays($paymentRatio < 1 ? 3 : 1)
                        ->min(CarbonImmutable::now());

                    $paymentService->record($business, $invoice, $creator, [
                        'payment_date' => $paymentDate->toDateString(),
                        'amount' => $paymentAmount,
                        'method' => $dayIndex % 2 === 0 ? PaymentMethod::Qr->value : PaymentMethod::BankTransfer->value,
                        'reference' => 'DEMO-'.$business->code.'-'.$date->format('ymd'),
                        'notes' => $paymentRatio < 1 ? 'Part payment received.' : 'Payment received in full.',
                    ]);
                }
            }

            $expenseDate = $month->day(min($currentMonth ? 1 : 20, $month->daysInMonth));
            foreach ($expenseCategories as $categoryIndex => $category) {
                if ($currentMonth && $categoryIndex > 0) {
                    continue;
                }

                $base = 1800 + ($businessIndex * 2300) + ($categoryIndex * 1400) + ((5 - $monthOffset) * 350);
                $business->expenses()->create([
                    'submitted_by' => $owner->id,
                    'approved_by' => $owner->id,
                    'category' => $category,
                    'vendor' => ['Cloud Nepal', 'Meta Ads', 'Office Supplier', 'Freelance Team'][$categoryIndex % 4],
                    'expense_date' => $expenseDate->toDateString(),
                    'amount' => $base,
                    'tax_amount' => 0,
                    'payment_method' => $categoryIndex % 2 === 0 ? PaymentMethod::BankTransfer : PaymentMethod::Qr,
                    'status' => ExpenseStatus::Approved,
                    'reference' => 'EXP-'.$business->code.'-'.$expenseDate->format('Ym').'-'.($categoryIndex + 1),
                    'notes' => 'Approved demo operating expense.',
                    'approved_at' => $expenseDate->endOfDay(),
                ]);
            }
        }
    }
}
