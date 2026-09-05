<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWriterRequest;
use App\Http\Resources\WriterResource;
use App\Models\Business;
use App\Models\User;
use App\Models\Writer;
use App\Services\AuditService;
use App\Services\WriterProfileService;
use App\Support\AuthorizesBusinessActions;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WriterController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(
        private readonly AuditService $audit,
        private readonly WriterProfileService $profiles,
    ) {}

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

        return response()->json($this->profiles->build($business, $writer, $range));
    }

    public function store(StoreWriterRequest $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Writers are only available for installment-category businesses.');

        $data = $request->validated();
        $writer = DB::transaction(function () use ($business, $data): Writer {
            $userId = $this->resolveLoginUserId($business, $data);

            return $business->writers()->create([
                ...collect($data)->except('password')->all(),
                'user_id' => $userId,
            ]);
        });

        $this->audit->record($request->user(), $business, 'writer.created', $writer, null, $writer->toArray(), $request);

        return response()->json(['message' => 'Writer added.', 'writer' => new WriterResource($writer)], 201);
    }

    public function update(StoreWriterRequest $request, Business $business, Writer $writer): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        $this->assertBusiness($business, $writer);
        $before = $writer->toArray();

        $data = $request->validated();
        DB::transaction(function () use ($writer, $business, $data): void {
            $userId = $this->resolveLoginUserId($business, $data, $writer);

            $writer->update([
                ...collect($data)->except('password')->all(),
                'user_id' => $userId ?? $writer->user_id,
            ]);
        });

        $this->audit->record($request->user(), $business, 'writer.updated', $writer, $before, $writer->fresh()->toArray(), $request);

        return response()->json(['message' => 'Writer updated.', 'writer' => new WriterResource($writer->fresh())]);
    }

    /**
     * Mirrors TeamController::store()'s find-or-create-by-email pattern: giving a
     * writer login access reuses an existing User by email, or creates one with the
     * given temporary password. Returns null when no login is being set up (or
     * changed) so the caller can leave an existing link untouched.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveLoginUserId(Business $business, array $data, ?Writer $writer = null): ?int
    {
        if (empty($data['password'])) {
            return null;
        }

        if (empty($data['email'])) {
            throw ValidationException::withMessages(['email' => 'An email is required to set up writer login.']);
        }

        $email = mb_strtolower($data['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user && $writer && $writer->user_id && $writer->user_id !== $user->id) {
            throw ValidationException::withMessages(['email' => 'This email belongs to a different account already.']);
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => $data['name'] ?? $writer?->name,
                'email' => $email,
                'phone' => $data['phone'] ?? $writer?->phone,
                'password' => $data['password'],
                'preferred_currency' => $business->currency,
            ]);
        }

        return $user->id;
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
