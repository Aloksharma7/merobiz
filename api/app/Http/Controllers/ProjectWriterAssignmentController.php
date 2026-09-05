<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignProjectWriterRequest;
use App\Http\Resources\WriterAssignmentResource;
use App\Models\Business;
use App\Models\Project;
use App\Services\ProjectWriterAssignmentService;
use App\Support\AuthorizesBusinessActions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectWriterAssignmentController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly ProjectWriterAssignmentService $assignments) {}

    public function index(Request $request, Business $business, Project $project): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Projects are only available for installment-category businesses.');
        abort_unless($project->business_id === $business->id, 404);

        return WriterAssignmentResource::collection(
            $project->writerAssignments()->with('writer')->orderByDesc('assigned_from')->get()
        );
    }

    public function store(AssignProjectWriterRequest $request, Business $business, Project $project): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Projects are only available for installment-category businesses.');
        abort_unless($project->business_id === $business->id, 404);

        $data = $request->validated();
        $assignments = $this->assignments->assign(
            $project,
            (int) $data['writer_id'],
            CarbonImmutable::parse($data['date']),
            $data['notes'] ?? null,
            $request->user(),
            $business,
        );

        return response()->json([
            'message' => 'Writer assignment updated.',
            'data' => WriterAssignmentResource::collection($assignments),
        ], 201);
    }
}
