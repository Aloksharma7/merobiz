<?php

namespace App\Http\Controllers;

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
}
