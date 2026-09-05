<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\ProjectWriterAssignmentService;
use App\Support\AuthorizesBusinessActions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(
        private readonly AuditService $audit,
        private readonly ProjectWriterAssignmentService $writerAssignments,
    ) {}

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertInstallment($business);

        $projects = $business->projects()
            ->when($request->filled('status'), fn ($query) => $query->where('work_status', $request->query('status')))
            ->when($request->filled('writer_id'), function ($query) use ($request): void {
                $writerId = $request->query('writer_id');
                $query->whereHas('writerAssignments', fn ($assignments) => $assignments
                    ->where('writer_id', $writerId)
                    ->whereNull('assigned_to'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('client_name', 'like', "%{$search}%")
                        ->orWhere('topic', 'like', "%{$search}%");
                });
            })
            ->with(['writerAssignments' => fn ($query) => $query->whereNull('assigned_to')->with('writer')])
            ->latest('id')
            ->paginate((int) $request->query('per_page', 50));

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertInstallment($business);

        $data = $request->validated();

        $project = DB::transaction(function () use ($data, $business, $request): Project {
            $startedOn = $data['started_on'] ?? now()->toDateString();
            $customer = $this->resolveCustomer($business, $data);

            $project = $business->projects()->create([
                'customer_id' => $customer->id,
                'created_by' => $request->user()->id,
                'client_name' => $data['client_name'],
                'client_phone' => $data['client_phone'] ?? null,
                'client_email' => $data['client_email'] ?? null,
                'started_on' => $startedOn,
                'topic' => $data['topic'],
                'course' => $data['course'],
                'work' => $data['work'],
                'work_status' => $data['work_status'] ?? 'started',
                'deadline' => $data['deadline'] ?? null,
                'deal_amount' => $data['deal_amount'],
                'writer_payment_amount' => $data['writer_payment_amount'] ?? 0,
            ]);

            if (! empty($data['writer_id'])) {
                $this->writerAssignments->assign($project, (int) $data['writer_id'], CarbonImmutable::parse($startedOn), null, $request->user(), $business);
            }

            return $project;
        });

        $this->audit->record($request->user(), $business, 'project.created', $project, null, $project->toArray(), $request);

        return response()->json([
            'message' => 'Project created.',
            'project' => new ProjectResource($project->load(['invoices', 'profitApprovals', 'writerAssignments.writer', 'customer'])),
        ], 201);
    }

    public function show(Request $request, Business $business, Project $project): ProjectResource
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertInstallment($business);
        $this->assertBusiness($business, $project);

        return new ProjectResource($project->load(['invoices', 'profitApprovals', 'writerAssignments.writer', 'customer']));
    }

    public function update(UpdateProjectRequest $request, Business $business, Project $project): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertInstallment($business);
        $this->assertBusiness($business, $project);

        $data = $request->validated();
        $before = $project->toArray();
        $writerId = array_key_exists('writer_id', $data) ? $data['writer_id'] : false;
        unset($data['writer_id']);

        $project->update($data);

        if ($writerId !== false && $writerId !== null) {
            $this->writerAssignments->assign($project, (int) $writerId, CarbonImmutable::now(), null, $request->user(), $business);
        }

        $this->audit->record($request->user(), $business, 'project.updated', $project, $before, $project->fresh()->toArray(), $request);

        return response()->json([
            'message' => 'Project updated.',
            'project' => new ProjectResource($project->fresh()->load(['invoices', 'profitApprovals', 'writerAssignments.writer', 'customer'])),
        ]);
    }

    public function destroy(Request $request, Business $business, Project $project): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertInstallment($business);
        $this->assertBusiness($business, $project);

        $before = $project->toArray();
        $project->delete();
        $this->audit->record($request->user(), $business, 'project.archived', $project, $before, null, $request);

        return response()->json(['message' => 'Project archived.']);
    }

    /** @param array<string, mixed> $data */
    private function resolveCustomer(Business $business, array $data): Customer
    {
        $customerId = $data['customer_id'] ?? null;
        if ($customerId) {
            $customer = $business->customers()->find($customerId);
            if (! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'The selected client does not belong to this business.']);
            }

            return $customer;
        }

        // No saved client picked — find or create one from the typed name so every
        // project's client is tracked on the Customers page with a profile.
        return $business->customers()->firstOrCreate(
            ['name' => trim((string) $data['client_name'])],
            [
                'phone' => $data['client_phone'] ?? null,
                'email' => $data['client_email'] ?? null,
                'opening_balance' => 0,
                'active' => true,
            ],
        );
    }

    private function assertInstallment(Business $business): void
    {
        abort_unless($business->isInstallment(), 422, 'Projects are only available for installment-category businesses.');
    }

    private function assertBusiness(Business $business, Project $project): void
    {
        abort_unless($project->business_id === $business->id, 404);
    }
}
