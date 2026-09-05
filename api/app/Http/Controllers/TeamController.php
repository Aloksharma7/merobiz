<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Enums\PayType;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DashboardService;
use App\Services\SalaryService;
use App\Support\AuthorizesBusinessActions;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(
        private readonly AuditService $audit,
        private readonly SalaryService $salary,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'team.manage');
        $range = DateRange::fromRequest($request);

        $memberships = $business->memberships()->with('user:id,name,email,phone')->orderByDesc('active')->get();

        $stats = Invoice::query()
            ->where('business_id', $business->id)
            ->whereIn('created_by', $memberships->pluck('user_id'))
            ->whereBetween('invoice_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->whereIn('status', DashboardService::LIVE_INVOICE_STATUSES)
            ->selectRaw('created_by, SUM(subtotal - discount_amount) as sales, SUM(commission_amount) as commission, COUNT(*) as invoice_count')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        // currentOwnershipFor() reuses an eager-loaded relation when present (see
        // Business::currentOwnershipFor) — load every relevant user's ownership
        // periods once here instead of one query per team member below.
        $business->load(['ownerships' => fn ($query) => $query->whereIn('user_id', $memberships->pluck('user_id'))]);

        $rows = $memberships->map(function (BusinessMembership $membership) use ($business, $range, $stats): array {
            $row = $stats->get($membership->user_id);
            $ownership = $business->currentOwnershipFor($membership->user, $range->end);
            $salary = $this->salary->summaryFor($membership);

            return [
                'id' => $membership->id,
                'business_id' => $business->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'phone' => $membership->user->phone,
                'initials' => $membership->user->initials,
                'role' => $membership->role->value,
                'full_control' => $membership->full_control,
                'is_founder' => $membership->user_id === $business->owner_id,
                'title' => $membership->title,
                'commission_rate' => (float) $membership->commission_rate,
                'active' => $membership->active,
                'joined_at' => $membership->joined_at?->toDateString(),
                'sales' => round((float) ($row->sales ?? 0), 2),
                'invoice_count' => (int) ($row->invoice_count ?? 0),
                'commission_earned' => round((float) ($row->commission ?? 0), 2),
                'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0.0,
                'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0.0,
                'pay_type' => $salary['pay_type'],
                'salary_amount' => $salary['salary_amount'],
                'salary_visible_to_staff' => $salary['salary_visible_to_staff'],
                'salary_paid_total' => $salary['paid_total'],
                'salary_pending' => $salary['pending'],
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $actorMembership = $this->requirePermission($request, 'team.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::enum(BusinessRole::class)],
            'full_control' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'pay_type' => ['sometimes', Rule::enum(PayType::class)],
            'salary_amount' => ['sometimes', 'numeric', 'min:0'],
            'salary_visible_to_staff' => ['sometimes', 'boolean'],
        ]);

        $role = BusinessRole::from($data['role']);
        abort_unless($role !== BusinessRole::Owner || $actorMembership->full_control, 403, 'Only a full-control owner can assign the owner role.');
        $fullControl = $role === BusinessRole::Owner && $actorMembership->full_control && ($data['full_control'] ?? false);

        $membership = DB::transaction(function () use ($business, $data, $role, $fullControl): BusinessMembership {
            $user = User::query()->where('email', mb_strtolower($data['email']))->first();

            if (! $user) {
                if (empty($data['password'])) {
                    throw ValidationException::withMessages(['password' => 'Set a temporary password for a new user.']);
                }

                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => mb_strtolower($data['email']),
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                    'preferred_currency' => $business->currency,
                ]);
            }

            $existing = $business->memberships()->where('user_id', $user->id)->first();
            if ($existing) {
                throw ValidationException::withMessages(['email' => 'This person is already a member of this business.']);
            }

            if ($role === BusinessRole::Employee) {
                $alreadyEmployedElsewhere = BusinessMembership::query()
                    ->where('user_id', $user->id)
                    ->where('role', BusinessRole::Employee->value)
                    ->where('active', true)
                    ->where('business_id', '!=', $business->id)
                    ->exists();

                if ($alreadyEmployedElsewhere) {
                    throw ValidationException::withMessages(['role' => 'This person is already an active employee of another business.']);
                }
            }

            return $business->memberships()->create([
                'user_id' => $user->id,
                'role' => $role,
                'full_control' => $fullControl,
                'title' => $data['title'] ?? null,
                'commission_rate' => $data['commission_rate'] ?? 0,
                'active' => true,
                'joined_at' => now()->toDateString(),
                'pay_type' => $data['pay_type'] ?? PayType::Commission->value,
                'salary_amount' => $data['salary_amount'] ?? 0,
                'salary_visible_to_staff' => $data['salary_visible_to_staff'] ?? false,
            ]);
        });

        $membership->load('user');
        $this->audit->record($request->user(), $business, 'team.member.added', $membership, null, $membership->toArray(), $request);

        return response()->json([
            'message' => 'Team member added.',
            'member' => [
                'id' => $membership->id,
                'business_id' => $business->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'full_control' => $membership->full_control,
                'title' => $membership->title,
                'commission_rate' => (float) $membership->commission_rate,
                'active' => $membership->active,
            ],
        ], 201);
    }

    public function update(Request $request, Business $business, BusinessMembership $membership): JsonResponse
    {
        $actorMembership = $this->requirePermission($request, 'team.manage');
        abort_unless($membership->business_id === $business->id, 404);

        $data = $request->validate([
            'role' => ['sometimes', Rule::enum(BusinessRole::class)],
            'full_control' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'pay_type' => ['sometimes', Rule::enum(PayType::class)],
            'salary_amount' => ['sometimes', 'numeric', 'min:0'],
            'salary_visible_to_staff' => ['sometimes', 'boolean'],
        ]);

        $isFounder = $membership->user_id === $business->owner_id;
        $actorIsFounder = $request->user()->id === $business->owner_id;
        abort_if($isFounder && ! $actorIsFounder && (
            array_key_exists('active', $data) || array_key_exists('role', $data) || array_key_exists('full_control', $data)
        ), 403, 'Only the person who created this business can change their own role, access or active status.');

        if (isset($data['role'])) {
            $data['role'] = BusinessRole::from($data['role']);
        }

        // Promoting someone to owner, demoting or deactivating an existing owner, and granting or
        // revoking full control are all ownership-level changes: only a full-control owner may make them.
        $targetIsOwner = $membership->role === BusinessRole::Owner;
        $roleTouchesOwnership = isset($data['role']) && ($data['role'] === BusinessRole::Owner || $targetIsOwner);
        $activeTouchesOwnership = array_key_exists('active', $data) && $targetIsOwner;
        $touchesFullControl = array_key_exists('full_control', $data);

        if ($roleTouchesOwnership || $activeTouchesOwnership || $touchesFullControl) {
            abort_unless($actorMembership->full_control, 403, 'Only a full-control owner can change ownership-level access.');
        }

        if ($touchesFullControl) {
            abort_if($isFounder && ! $data['full_control'], 422, 'The business founder always keeps full control.');
            $data['full_control'] = $membership->role === BusinessRole::Owner && $data['full_control'];
        }

        $deactivating = array_key_exists('active', $data) && ! $data['active'] && $membership->active;
        $demoting = isset($data['role']) && $membership->role === BusinessRole::Owner && $data['role'] !== BusinessRole::Owner;

        if (($deactivating || $demoting) && $membership->role === BusinessRole::Owner) {
            $otherActiveOwners = $business->memberships()
                ->where('id', '!=', $membership->id)
                ->where('role', BusinessRole::Owner->value)
                ->where('active', true)
                ->exists();

            abort_unless($otherActiveOwners, 422, 'A business must keep at least one active owner.');
        }

        $before = $membership->toArray();
        $membership->update($data);
        $this->audit->record($request->user(), $business, 'team.member.updated', $membership, $before, $membership->fresh()->toArray(), $request);

        return response()->json([
            'message' => 'Team member updated.',
            'member' => [
                'id' => $membership->id,
                'business_id' => $business->id,
                'user_id' => $membership->user_id,
                'role' => $membership->role->value,
                'full_control' => $membership->full_control,
                'title' => $membership->title,
                'commission_rate' => (float) $membership->commission_rate,
                'active' => $membership->active,
                'pay_type' => $membership->pay_type->value,
                'salary_amount' => (float) $membership->salary_amount,
                'salary_visible_to_staff' => $membership->salary_visible_to_staff,
            ],
        ]);
    }
}
