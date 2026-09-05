<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWriterRequest;
use App\Http\Resources\WriterResource;
use App\Models\Business;
use App\Models\Project;
use App\Models\Writer;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WriterController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'writers.manage');

        $writers = $business->writers()
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
            ->paginate((int) $request->query('per_page', 50));

        return WriterResource::collection($writers);
    }

    public function show(Request $request, Business $business, Writer $writer): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertBusiness($business, $writer);

        $range = DateRange::fromRequest($request);

        $projects = $business->projects()
            ->whereHas('writerAssignments', fn ($query) => $query->where('writer_id', $writer->id))
            ->with(['writerAssignments' => fn ($query) => $query->where('writer_id', $writer->id)->orderByDesc('assigned_from')])
            ->latest('id')
            ->get();

        $currentProjects = $projects->filter(fn (Project $project) => $project->writerAssignments
            ->contains(fn ($assignment) => $assignment->assigned_to === null));

        $totalPaid = (float) $writer->payments()->sum('amount');
        $totalDue = (float) $currentProjects->sum(fn (Project $project) => $project->writerDueAmount());

        $periodPayments = $writer->payments()
            ->with('project:id,client_name')
            ->whereBetween('paid_on', [$range->start->toDateString(), $range->end->toDateString()])
            ->orderByDesc('paid_on')
            ->get();

        return response()->json([
            'writer' => new WriterResource($writer),
            'period' => $range->toArray(),
            'stats' => [
                'total_projects' => $projects->count(),
                'current_projects' => $currentProjects->count(),
                'total_agreed' => round((float) $projects->sum('writer_payment_amount'), 2),
                'total_paid' => round($totalPaid, 2),
                'total_due' => round($totalDue, 2),
                'period_paid' => round((float) $periodPayments->sum('amount'), 2),
            ],
            'payments' => $periodPayments->map(fn ($payment): array => [
                'id' => $payment->id,
                'project_id' => $payment->project_id,
                'client_name' => $payment->project?->client_name,
                'paid_on' => $payment->paid_on->toDateString(),
                'amount' => (float) $payment->amount,
                'notes' => $payment->notes,
            ])->values(),
            'projects' => $projects->map(function (Project $project) use ($writer) {
                $assignment = $project->writerAssignments->first();
                $isCurrent = $assignment && $assignment->assigned_to === null;
                $paidForThisProject = (float) $project->writerPayments()->where('writer_id', $writer->id)->sum('amount');

                return [
                    'id' => $project->id,
                    'client_name' => $project->client_name,
                    'topic' => $project->topic,
                    'course' => $project->course,
                    'work' => $project->work,
                    'work_status' => $project->work_status->value,
                    'writer_payment_amount' => (float) $project->writer_payment_amount,
                    'writer_paid_amount' => round($paidForThisProject, 2),
                    'writer_due_amount' => $isCurrent ? round($project->writerDueAmount(), 2) : null,
                    'is_current' => $isCurrent,
                    'assigned_from' => $assignment?->assigned_from->toDateString(),
                    'assigned_to' => $assignment?->assigned_to?->toDateString(),
                ];
            })->values(),
        ]);
    }

    public function store(StoreWriterRequest $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Writers are only available for installment-category businesses.');
        $writer = $business->writers()->create($request->validated());
        $this->audit->record($request->user(), $business, 'writer.created', $writer, null, $writer->toArray(), $request);

        return response()->json(['message' => 'Writer added.', 'writer' => new WriterResource($writer)], 201);
    }

    public function update(StoreWriterRequest $request, Business $business, Writer $writer): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertBusiness($business, $writer);
        $before = $writer->toArray();
        $writer->update($request->validated());
        $this->audit->record($request->user(), $business, 'writer.updated', $writer, $before, $writer->fresh()->toArray(), $request);

        return response()->json(['message' => 'Writer updated.', 'writer' => new WriterResource($writer->fresh())]);
    }

    public function destroy(Request $request, Business $business, Writer $writer): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertBusiness($business, $writer);
        $before = $writer->toArray();
        $writer->delete();
        $this->audit->record($request->user(), $business, 'writer.archived', $writer, $before, null, $request);

        return response()->json(['message' => 'Writer archived.']);
    }

    private function assertBusiness(Business $business, Writer $writer): void
    {
        abort_unless($writer->business_id === $business->id, 404);
    }
}
