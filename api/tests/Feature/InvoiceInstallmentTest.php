<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceInstallmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_installment_amounts_must_sum_to_the_invoice_total(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Thesis Client',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'description' => 'Thesis writing package', 'quantity' => 1, 'unit_price' => 1000,
                'unit_cost' => 0, 'discount_amount' => 0, 'tax_rate' => 0,
            ]],
            'installments' => [
                ['due_date' => today()->toDateString(), 'amount' => 400],
                ['due_date' => today()->addDays(30)->toDateString(), 'amount' => 400],
            ],
        ]);
    }

    public function test_installment_schedule_reflects_partial_payment_against_the_first_due_installment(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);

        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Thesis Client',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'description' => 'Thesis writing package', 'quantity' => 1, 'unit_price' => 1000,
                'unit_cost' => 0, 'discount_amount' => 0, 'tax_rate' => 0,
            ]],
            'installments' => [
                ['due_date' => today()->toDateString(), 'amount' => 400, 'notes' => 'Down payment'],
                ['due_date' => today()->addDays(30)->toDateString(), 'amount' => 600],
            ],
        ]);

        $this->assertCount(2, $invoice->installments);

        app(PaymentService::class)->record($business, $invoice, $owner, [
            'payment_date' => today()->toDateString(),
            'amount' => 400,
            'method' => 'cash',
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/installments")->assertOk();
        $response->assertJsonPath('data.0.status', 'paid');
        $response->assertJsonPath('data.0.paid_amount', 400);
        $response->assertJsonPath('data.1.status', 'pending');
        $response->assertJsonPath('data.1.paid_amount', 0);
    }

    private function business(User $owner): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten-'.uniqid(),
            'code' => 'ERC'.rand(100, 999), 'business_type' => 'service', 'currency' => 'NPR',
            'invoice_prefix' => 'ERC', 'default_tax_rate' => 0, 'status' => 'active',
            'settings' => ['features' => ['installments' => true]],
        ]);
    }
}
