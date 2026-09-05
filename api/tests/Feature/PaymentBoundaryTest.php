<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function standardBusiness(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'TechChamp Software', 'slug' => 'techchamp-'.uniqid(), 'code' => 'TC'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'TCS',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_paying_the_exact_outstanding_balance_is_accepted(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = $this->standardBusiness($owner);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Raju Thapa', 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'AI consultancy', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');

        $this->assertEquals(5000.0, $invoice['balance_amount']);

        // Paying the exact displayed outstanding balance must succeed, not be rejected
        // as "greater than" it due to a stale snapshot or a too-strict comparison.
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'qr',
        ])->assertCreated();

        $fresh = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")->assertOk()->json('data');
        $this->assertEquals(0.0, $fresh['balance_amount']);
        $this->assertSame('paid', $fresh['status']);
    }

    public function test_paying_meaningfully_more_than_the_balance_is_still_rejected_with_a_clear_message(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->standardBusiness($owner);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Sita Gurung', 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Web design', 'quantity' => 1, 'unit_price' => 3000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');

        $response = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 3500, 'method' => 'cash',
        ])->assertUnprocessable();

        $message = $response->json('errors.amount.0');
        $this->assertStringContainsString('3,500.00', $message);
        $this->assertStringContainsString('3,000.00', $message);
    }
}
