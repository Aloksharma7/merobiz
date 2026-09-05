<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalIncomeEntryRequest;
use App\Http\Resources\PersonalIncomeEntryResource;
use App\Models\PersonalIncomeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PersonalIncomeEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $entries = $request->user()->personalIncomeEntries()
            ->with('source:id,name,type')
            ->when($request->filled('source_id'), fn ($query) => $query->where('source_id', $request->query('source_id')))
            ->latest('entry_date')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20));

        return PersonalIncomeEntryResource::collection($entries);
    }

    public function store(StorePersonalIncomeEntryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $source = $request->user()->personalIncomeSources()->find($data['source_id']);

        if (! $source) {
            throw ValidationException::withMessages(['source_id' => 'Choose a valid income source.']);
        }

        $entry = $request->user()->personalIncomeEntries()->create($data);
        $entry->load('source:id,name,type');

        return response()->json([
            'message' => 'Income recorded.',
            'entry' => new PersonalIncomeEntryResource($entry),
        ], 201);
    }

    public function destroy(Request $request, PersonalIncomeEntry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
        $entry->delete();

        return response()->json(['message' => 'Income entry deleted.']);
    }
}
