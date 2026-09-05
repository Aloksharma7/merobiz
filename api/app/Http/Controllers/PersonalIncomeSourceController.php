<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalIncomeSourceRequest;
use App\Http\Resources\PersonalIncomeSourceResource;
use App\Models\PersonalIncomeSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonalIncomeSourceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sources = $request->user()->personalIncomeSources()
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')
            ->get();

        return PersonalIncomeSourceResource::collection($sources);
    }

    public function store(StorePersonalIncomeSourceRequest $request): JsonResponse
    {
        $source = $request->user()->personalIncomeSources()->create($request->validated());

        return response()->json([
            'message' => 'Income source added.',
            'source' => new PersonalIncomeSourceResource($source),
        ], 201);
    }

    public function update(StorePersonalIncomeSourceRequest $request, PersonalIncomeSource $source): JsonResponse
    {
        $this->assertOwner($request, $source);
        $source->update($request->validated());

        return response()->json(['message' => 'Income source updated.', 'source' => new PersonalIncomeSourceResource($source->fresh())]);
    }

    public function destroy(Request $request, PersonalIncomeSource $source): JsonResponse
    {
        $this->assertOwner($request, $source);
        $source->delete();

        return response()->json(['message' => 'Income source archived.']);
    }

    private function assertOwner(Request $request, PersonalIncomeSource $source): void
    {
        abort_unless($source->user_id === $request->user()->id, 404);
    }
}
