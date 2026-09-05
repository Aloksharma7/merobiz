<?php

namespace App\Http\Controllers;

use App\Enums\ProjectWorkStatus;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Project;
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

    public function show(Request $request, Business $business, Customer $customer): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless($membership->allows('customers.view') || $membership->allows('customers.manage'), 403);
        $this->assertBusiness($business, $customer);

        $customer->loadSum(['invoices as outstanding_balance' => fn ($query) => $query
            ->whereIn('status', ['issued', 'partial', 'overdue'])], 'balance_amount');

        if ($business->isInstallment()) {
            $projects = $customer->projects()
                ->with([
                    'writerAssignments' => fn ($query) => $query->whereNull('assigned_to')->with('writer'),
                    'invoices:id,project_id,status,paid_amount',
                    'refunds:id,project_id,amount',
                ])
                ->latest('id')
                ->get();

            $completed = $projects->filter(fn (Project $project) => $project->work_status === ProjectWorkStatus::Approved);
            $cancelled = $projects->filter(fn (Project $project) => $project->work_status === ProjectWorkStatus::Cancelled);
            $inProgress = $projects->count() - $completed->count() - $cancelled->count();

            $payments = $customer->payments()
                ->with('invoice.project:id,topic,course,work')
                ->latest('payment_date')
                ->limit(50)
                ->get();

            return response()->json([
                'customer' => new CustomerResource($customer),
                'stats' => [
                    'total_projects' => $projects->count(),
                    'completed_projects' => $completed->count(),
                    'cancelled_projects' => $cancelled->count(),
                    'in_progress_projects' => $inProgress,
                    'total_deal_amount' => round((float) $projects->sum('deal_amount'), 2),
                    'total_collected' => round((float) $projects->sum(fn (Project $project) => $project->collectedAmount()), 2),
                    'total_refunded' => round((float) $projects->sum(fn (Project $project) => $project->refundedAmount()), 2),
                    'total_due' => round((float) $projects->sum(fn (Project $project) => $project->dueAmount()), 2),
                ],
                'projects' => $projects->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'topic' => $project->topic,
                    'course' => $project->course,
                    'work' => $project->work,
                    'work_status' => $project->work_status->value,
                    'deal_amount' => (float) $project->deal_amount,
                    'collected_amount' => round($project->collectedAmount(), 2),
                    'refunded_amount' => round($project->refundedAmount(), 2),
                    'due_amount' => round($project->dueAmount(), 2),
                    'writer' => ($writer = $project->currentWriter()) ? ['id' => $writer->id, 'name' => $writer->name] : null,
                ])->values(),
                'payments' => $payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'project_id' => $payment->invoice?->project_id,
                    'project_topic' => $payment->invoice?->project?->topic,
                    'amount' => (float) $payment->amount,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'method' => $payment->method->value,
                    'notes' => $payment->notes,
                ])->values(),
            ]);
        }

        $invoices = $customer->invoices()->with('creator:id,name')->latest('invoice_date')->limit(50)->get();

        return response()->json([
            'customer' => new CustomerResource($customer),
            'stats' => [
                'total_invoices' => $customer->invoices()->count(),
                'total_invoiced' => round((float) $customer->invoices()->sum('total_amount'), 2),
                'total_paid' => round((float) $customer->invoices()->sum('paid_amount'), 2),
                'outstanding' => round((float) $customer->getAttribute('outstanding_balance'), 2),
            ],
            'invoices' => $invoices->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'status' => $invoice->status->value,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance_amount' => (float) $invoice->balance_amount,
            ])->values(),
        ]);
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
