<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        abort_unless($membership->allows('customers.view') || $membership->allows('customers.manage'), 403);

        $employeeOnly = $membership->role->value === 'employee';

        $customers = $business->customers()
            ->withSum(['invoices as outstanding_balance' => function ($query) use ($request, $employeeOnly): void {
                $query->whereIn('status', ['issued', 'partial', 'overdue']);
                if ($employeeOnly) {
                    $query->where('created_by', $request->user()->id);
                }
            }], 'balance_amount')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 20));

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'customers.manage');
        $customer = $business->customers()->create($request->validated());
        $this->audit->record($request->user(), $business, 'customer.created', $customer, null, $customer->toArray(), $request);

        return response()->json([
            'message' => 'Customer added.',
            'customer' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Request $request, Business $business, Customer $customer): CustomerResource
    {
        $this->assertBusiness($business, $customer);
        return new CustomerResource($customer);
    }

    public function update(StoreCustomerRequest $request, Business $business, Customer $customer): JsonResponse
    {
        $this->requirePermission($request, 'customers.manage');
        $this->assertBusiness($business, $customer);
        $before = $customer->toArray();
        $customer->update($request->validated());
        $this->audit->record($request->user(), $business, 'customer.updated', $customer, $before, $customer->fresh()->toArray(), $request);

        return response()->json(['message' => 'Customer updated.', 'customer' => new CustomerResource($customer->fresh())]);
    }

    public function destroy(Request $request, Business $business, Customer $customer): JsonResponse
    {
        $this->requirePermission($request, 'customers.manage');
        $this->assertBusiness($business, $customer);
        $before = $customer->toArray();
        $customer->delete();
        $this->audit->record($request->user(), $business, 'customer.archived', $customer, $before, null, $request);

        return response()->json(['message' => 'Customer archived.']);
    }

    private function assertBusiness(Business $business, Customer $customer): void
    {
        abort_unless($customer->business_id === $business->id, 404);
    }
}
