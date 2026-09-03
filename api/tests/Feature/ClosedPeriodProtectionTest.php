<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ProfitClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClosedPeriodProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_cannot_be_backdated_into_a_closed_profit_period(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Protected Business',
            'slug' => 'protected-business',
            'code' => 'PROT',
            'business_type' => 'service',
            'currency' => 'NPR',
            'invoice_prefix' => 'PROT',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);
        $business->ownerships()->create([
            'user_id' => $owner->id,
            'ownership_percent' => 100,
            'profit_share_percent' => 100,
            'effective_from' => today()->subYear()->toDateString(),
        ]);

        $closedDate = today()->subMonth()->startOfMonth();
        app(ProfitClosingService::class)->close($business, $owner, [
            'start_date' => $closedDate->toDateString(),
            'end_date' => $closedDate->endOfMonth()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        app(InvoiceService::class)->create($business, $owner, [
            'invoice_date' => $closedDate->addDays(3)->toDateString(),
            'status' => 'issued',
            'discount_amount' => 0,
            'items' => [[
                'description' => 'Backdated service',
                'quantity' => 1,
                'unit_price' => 100,
                'unit_cost' => 20,
                'tax_rate' => 0,
            ]],
        ]);
    }
}
