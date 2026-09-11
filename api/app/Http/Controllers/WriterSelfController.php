<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WriterProfileService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WriterSelfController extends Controller
{
    public function __construct(private readonly WriterProfileService $profiles) {}

    /**
     * A writer's own read-only dashboard — their current and past files, what
     * they've been paid, and what's still due. No {business}/{writer} route
     * params: the writer is derived entirely from the authenticated user's own
     * linked roster entry, so there's nothing to authorize beyond being logged in.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $writer = $request->user()->writer()->with('business')->first();
        abort_unless($writer, 403, 'This account is not linked to a writer profile.');

        $range = DateRange::fromRequest($request);

        return response()->json($this->profiles->build($writer->business, $writer, $range));
    }

    /**
     * The full detail of one of the writer's own files — never someone else's,
     * and never one they were never assigned to, whether or not they're still
     * the current writer on it.
     */
    public function project(Request $request, Project $project): JsonResponse
    {
        $writer = $request->user()->writer()->with('business')->first();
        abort_unless($writer, 403, 'This account is not linked to a writer profile.');
        abort_unless($project->business_id === $writer->business_id, 404);
        abort_unless($project->writerAssignments()->where('writer_id', $writer->id)->exists(), 404);

        return response()->json($this->profiles->projectDetail($writer, $project));
    }
}
