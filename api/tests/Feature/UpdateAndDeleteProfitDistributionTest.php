<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateAndDeleteProfitDistributionTest extends TestCase
{
    use RefreshDatabase;

    private function closedPeriodAllocation(User $owner, Business $business): array
    {
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'unit', 'sale_price' => 10000, 'cost_price' => 0, 'tax_rate' => 0, 'active' => true,
        ]);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->subDays(5)->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->subDays(5)->toDateString(), 'amount' => 10000, 'method' => 'bank_transfer',
        ])->assertCreated();

        $period = $this->postJson("/api/businesses/{$business->id}/profit-periods", [
            'start_date' => today()->subDays(6)->toDateString(), 'end_date' => today()->subDays(4)->toDateString(),
        ])->assertCreated()->json('period');

        return $period['allocations'][0];
    }

    public function test_an_admin_can_edit_a_distributions_amount(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadd1@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Distribution Edit Co', 'slug' => 'dist-edit-co-'.uniqid(), 'code' => 'DE'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'DEC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->ownerships()->create([
            'user_id' => $owner->id, 'ownership_percent' => 100, 'profit_share_percent' => 100,
            'effective_from' => today()->subYear()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $allocation = $this->closedPeriodAllocation($owner, $business);

        $distribution = $this->postJson("/api/businesses/{$business->id}/profit-distributions", [
            'profit_allocation_id' => $allocation['id'], 'distribution_date' => today()->toDateString(),
            'amount' => 2000, 'method' => 'bank_transfer',
        ])->assertCreated()->json('distribution');

        $this->patchJson("/api/businesses/{$business->id}/profit-distributions/{$distribution['id']}", [
            'profit_allocation_id' => $allocation['id'], 'distribution_date' => today()->toDateString(),
            'amount' => 3000, 'method' => 'cash',
        ])->assertOk();

        $fresh = $this->getJson("/api/businesses/{$business->id}/profit-periods")->assertOk()->json('data.0');
        $this->assertEquals(3000.0, $fresh['allocations'][0]['distributed_amount']);
    }

    public function test_an_admin_can_undo_a_distribution(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadd2@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Distribution Undo Co', 'slug' => 'dist-undo-co-'.uniqid(), 'code' => 'DU'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'DUC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->ownerships()->create([
            'user_id' => $owner->id, 'ownership_percent' => 100, 'profit_share_percent' => 100,
            'effective_from' => today()->subYear()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $allocation = $this->closedPeriodAllocation($owner, $business);

        $distribution = $this->postJson("/api/businesses/{$business->id}/profit-distributions", [
            'profit_allocation_id' => $allocation['id'], 'distribution_date' => today()->toDateString(),
            'amount' => 2500, 'method' => 'bank_transfer',
        ])->assertCreated()->json('distribution');

        $this->deleteJson("/api/businesses/{$business->id}/profit-distributions/{$distribution['id']}")->assertOk();

        $fresh = $this->getJson("/api/businesses/{$business->id}/profit-periods")->assertOk()->json('data.0');
        $this->assertEquals(0.0, $fresh['allocations'][0]['distributed_amount']);
        $this->assertDatabaseMissing('profit_distributions', ['id' => $distribution['id']]);
    }

    public function test_an_employee_cannot_edit_or_delete_a_distribution(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uadd3@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-uadd3@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Distribution Guard Co', 'slug' => 'dist-guard-co-'.uniqid(), 'code' => 'DG'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'DGC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);
        $business->ownerships()->create([
            'user_id' => $owner->id, 'ownership_percent' => 100, 'profit_share_percent' => 100,
            'effective_from' => today()->subYear()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $allocation = $this->closedPeriodAllocation($owner, $business);
        $distribution = $this->postJson("/api/businesses/{$business->id}/profit-distributions", [
            'profit_allocation_id' => $allocation['id'], 'distribution_date' => today()->toDateString(),
            'amount' => 1000, 'method' => 'cash',
        ])->assertCreated()->json('distribution');

        Sanctum::actingAs($employee);
        $this->patchJson("/api/businesses/{$business->id}/profit-distributions/{$distribution['id']}", [
            'profit_allocation_id' => $allocation['id'], 'distribution_date' => today()->toDateString(),
            'amount' => 1500, 'method' => 'cash',
        ])->assertForbidden();
        $this->deleteJson("/api/businesses/{$business->id}/profit-distributions/{$distribution['id']}")->assertForbidden();
    }
}
