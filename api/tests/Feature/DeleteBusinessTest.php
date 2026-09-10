<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteBusinessTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Delete Biz Co', 'slug' => 'delete-biz-co-'.uniqid(), 'code' => 'DB'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'DB', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
    }

    public function test_a_full_control_owner_can_delete_the_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dbz1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/businesses/{$business->id}")->assertOk();

        $this->assertSoftDeleted('businesses', ['id' => $business->id]);
    }

    public function test_an_admin_cannot_delete_the_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dbz2@example.test', 'password' => 'password']);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-dbz2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create(['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true]);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/businesses/{$business->id}")->assertForbidden();

        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'deleted_at' => null]);
    }

    public function test_a_co_owner_without_full_control_cannot_delete_the_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dbz3@example.test', 'password' => 'password']);
        $coOwner = User::query()->create(['name' => 'Co Owner', 'email' => 'coowner-dbz3@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create(['user_id' => $coOwner->id, 'role' => BusinessRole::Owner, 'full_control' => false, 'active' => true]);

        Sanctum::actingAs($coOwner);
        $this->deleteJson("/api/businesses/{$business->id}")->assertForbidden();

        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'deleted_at' => null]);
    }
}
