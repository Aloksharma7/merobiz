<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedPanInvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_businesses_sharing_a_pan_never_reuse_an_invoice_number(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $turnitin = $this->business($owner, 'Turnitin Nepal', 'TNP', '609876543');
        $claude = $this->business($owner, 'Claude Nepal', 'CNP', '609876543');
        $unrelated = $this->business($owner, 'Solo Shop', 'SOLO', null);

        foreach ([$turnitin, $claude, $unrelated] as $business) {
            $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);
        }

        $invoiceService = app(InvoiceService::class);

        $first = $this->invoice($invoiceService, $turnitin, $owner);
        $second = $this->invoice($invoiceService, $claude, $owner);
        $third = $this->invoice($invoiceService, $turnitin, $owner);
        $soloFirst = $this->invoice($invoiceService, $unrelated, $owner);
        $soloSecond = $this->invoice($invoiceService, $unrelated, $owner);

        $this->assertSame('1', $first->invoice_number);
        $this->assertSame('2', $second->invoice_number);
        $this->assertSame('3', $third->invoice_number);
        // A business with no shared PAN keeps the original per-business prefixed format untouched.
        $this->assertSame('SOLO-000001', $soloFirst->invoice_number);
        $this->assertSame('SOLO-000002', $soloSecond->invoice_number);
    }

    private function invoice(InvoiceService $service, Business $business, User $owner)
    {
        return $service->create($business, $owner, [
            'customer_name' => 'Client',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'description' => 'Service', 'quantity' => 1, 'unit_price' => 500,
                'unit_cost' => 0, 'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);
    }

    private function business(User $owner, string $name, string $code, ?string $pan): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id, 'name' => $name, 'slug' => strtolower($code).'-'.uniqid(),
            'code' => $code, 'business_type' => 'service', 'currency' => 'NPR', 'pan_number' => $pan,
            'invoice_prefix' => $code, 'default_tax_rate' => 0, 'status' => 'active',
        ]);
    }
}
