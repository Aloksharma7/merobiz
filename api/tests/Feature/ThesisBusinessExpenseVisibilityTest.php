<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThesisBusinessExpenseVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_sees_every_expense_in_an_installment_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tbev1@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-tbev1a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-tbev1b@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Thesis Expense Co', 'slug' => 'thesis-expense-co-'.uniqid(), 'code' => 'TE'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'TEC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true],
            ['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true],
            ['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employeeOne);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Printing', 'expense_date' => today()->toDateString(), 'amount' => 500, 'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($employeeTwo);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Binding', 'expense_date' => today()->toDateString(), 'amount' => 300, 'payment_method' => 'cash',
        ])->assertCreated();

        // Employee Two sees both expenses — their own and Employee One's —
        // not just what they personally submitted.
        $this->getJson("/api/businesses/{$business->id}/expenses")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_employee_still_sees_only_their_own_expenses_in_a_standard_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tbev2@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-tbev2a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-tbev2b@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Standard Expense Co', 'slug' => 'standard-expense-co-'.uniqid(), 'code' => 'SE'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'SEC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true],
            ['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true],
            ['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employeeOne);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Office supplies', 'expense_date' => today()->toDateString(), 'amount' => 500, 'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($employeeTwo);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Travel', 'expense_date' => today()->toDateString(), 'amount' => 300, 'payment_method' => 'cash',
        ])->assertCreated();

        // Unchanged behavior for a standard business — each employee only sees
        // what they personally submitted.
        $this->getJson("/api/businesses/{$business->id}/expenses")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'Travel');
    }
}
