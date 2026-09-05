<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private function businessFor(User $owner, string $name): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(), 'code' => strtoupper(substr($name, 0, 3)).rand(1000, 9999),
            'business_type' => 'product', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'INV',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_auth_me_bundles_the_businesses_list_in_one_response(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $businessA = $this->businessFor($owner, 'Shop A');
        $businessB = $this->businessFor($owner, 'Shop B');

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/auth/me')->assertOk()->json();

        $this->assertArrayHasKey('businesses', $response);
        $names = collect($response['businesses'])->pluck('name')->all();
        $this->assertContains('Shop A', $names);
        $this->assertContains('Shop B', $names);
    }

    public function test_auth_me_does_not_issue_a_query_per_business_in_the_list(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        for ($i = 0; $i < 8; $i++) {
            $this->businessFor($owner, 'Business '.$i);
        }

        Sanctum::actingAs($owner);

        DB::enableQueryLog();
        $this->getJson('/api/auth/me')->assertOk()->assertJsonCount(8, 'businesses');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // With 8 businesses, a per-business membership/ownership lookup would push
        // this well past 20 queries. Eager-loading keeps it flat regardless of count.
        $this->assertLessThan(15, $queryCount, "auth/me issued {$queryCount} queries for 8 businesses — check for a reintroduced N+1.");
    }

    public function test_a_user_with_no_businesses_gets_an_empty_list_not_an_error(): void
    {
        $user = User::query()->create(['name' => 'Newcomer', 'email' => 'newcomer@example.test', 'password' => 'password']);

        Sanctum::actingAs($user);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('businesses', []);
    }
}
