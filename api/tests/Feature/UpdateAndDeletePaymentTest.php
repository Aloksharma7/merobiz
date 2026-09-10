<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateAndDeletePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Payment Edit Co', 'slug' => 'payment-edit-co-'.uniqid(), 'code' => 'PE'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'PEC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_edit_a_payments_amount(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadp1@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $payment = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 4000, 'method' => 'cash',
        ])->assertCreated()->json('payment');

        $this->patchJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$payment['id']}", [
            'payment_date' => today()->toDateString(), 'amount' => 6000, 'method' => 'bank_transfer',
        ])->assertOk();

        $fresh = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")->assertOk()->json('data');
        $this->assertEquals(6000.0, $fresh['paid_amount']);
        $this->assertEquals(4000.0, $fresh['balance_amount']);
        $this->assertSame('partial', $fresh['status']);
    }

    public function test_editing_a_payment_above_the_true_outstanding_balance_is_rejected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadp2@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $paymentOne = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 3000, 'method' => 'cash',
        ])->assertCreated()->json('payment');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 3000, 'method' => 'cash',
        ])->assertCreated();
        // 4000 still outstanding (10000 - 3000 - 3000). Raising payment one to 5000
        // would need 5000, but only 3000 (its own) + 4000 (remaining) = 7000 is free.
        $this->patchJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$paymentOne['id']}", [
            'payment_date' => today()->toDateString(), 'amount' => 8000, 'method' => 'cash',
        ])->assertUnprocessable();
    }

    public function test_an_admin_can_undo_a_payment(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadp3@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $payment = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'cash',
        ])->assertCreated()->json('payment');

        $fresh = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")->assertOk()->json('data');
        $this->assertSame('paid', $fresh['status']);

        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$payment['id']}")->assertOk();

        $fresh = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")->assertOk()->json('data');
        $this->assertEquals(0.0, $fresh['paid_amount']);
        $this->assertEquals(10000.0, $fresh['balance_amount']);
        $this->assertSame('issued', $fresh['status']);
        $this->assertDatabaseMissing('payments', ['id' => $payment['id']]);
    }

    public function test_an_employee_without_payments_manage_cannot_edit_or_delete_someone_elses_payment(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadp4@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-uadp4@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $payment = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 4000, 'method' => 'cash',
        ])->assertCreated()->json('payment');

        Sanctum::actingAs($employee);
        $this->patchJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$payment['id']}", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'cash',
        ])->assertForbidden();
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$payment['id']}")->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $payment['id'], 'amount' => 4000]);
    }
}
