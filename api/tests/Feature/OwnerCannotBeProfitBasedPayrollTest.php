<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerCannotBeProfitBasedPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Owner Payroll Guard Co', 'slug' => 'owner-payroll-guard-co-'.uniqid(), 'code' => 'OG'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'OG', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_adding_a_new_owner_with_profit_based_pay_type_is_rejected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-opg1@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Co-owner', 'email' => 'coowner-opg1@example.test', 'password' => 'password123',
            'role' => 'owner', 'pay_type' => 'profit_share', 'ownership_percent' => 0, 'profit_share_percent' => 20,
        ])->assertUnprocessable()->assertJsonValidationErrors(['pay_type']);
    }

    public function test_switching_an_existing_owner_to_profit_based_pay_type_is_rejected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-opg2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->where('user_id', $owner->id)->firstOrFail();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}", [
            'pay_type' => 'profit_share',
        ])->assertUnprocessable()->assertJsonValidationErrors(['pay_type']);
    }

    public function test_an_employee_can_still_be_set_to_profit_based_pay_type(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-opg3@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Employee', 'email' => 'employee-opg3@example.test', 'password' => 'password123',
            'role' => 'employee', 'pay_type' => 'profit_share', 'ownership_percent' => 0, 'profit_share_percent' => 20,
        ])->assertCreated();
    }
}
