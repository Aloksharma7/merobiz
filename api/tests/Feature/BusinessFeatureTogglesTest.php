<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessFeatureTogglesTest extends TestCase
{
    use RefreshDatabase;

    public function test_installments_are_rejected_until_the_business_enables_the_feature(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Aimers AI', 'slug' => 'aimers-ai', 'code' => 'AAI',
            'business_type' => 'digital_subscription', 'currency' => 'NPR', 'invoice_prefix' => 'AAI',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Client',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'description' => 'Subscription', 'quantity' => 1, 'unit_price' => 1000,
                'discount_amount' => 0, 'tax_rate' => 0,
            ]],
            'installments' => [
                ['due_date' => today()->toDateString(), 'amount' => 400],
                ['due_date' => today()->addDays(30)->toDateString(), 'amount' => 600],
            ],
        ]);
    }

    public function test_writer_assignment_is_rejected_for_a_standard_category_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Aimers AI', 'slug' => 'aimers-ai-2', 'code' => 'AAI2',
            'business_type' => 'digital_subscription', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'AAI2',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);
        $writer = $business->writers()->create(['name' => 'Writer A', 'active' => true]);

        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Client',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'description' => 'Subscription', 'quantity' => 1, 'unit_price' => 1000,
                'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);
        $item = $invoice->items->first();

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/items/{$item->id}/writers", [
            'writer_ids' => [$writer->id],
            'date' => today()->toDateString(),
        ])->assertUnprocessable();
    }
}
