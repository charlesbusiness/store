<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMemberRequest;
use App\Http\Requests\Api\UpdateMemberBranchesRequest;
use App\Http\Requests\Api\UpdateMembershipRequest;
use App\Http\Requests\Api\UpdateTenantSettingsRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MemberAddedNotification;
use App\Services\Auth\MemberInvitationService;
use App\Support\TenantAuthorizer;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantAuthorizer $authorizer,
        private readonly MemberInvitationService $memberInvitations,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $tenant = $this->context->tenant();

        return response()->json([
            'data' => [
                'tenant' => $tenant,
                'role' => $this->authorizer->role($request->user()),
                'branches' => $this->authorizer->allowedBranches($request->user(), $tenant),
            ],
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);
        $tenant = $this->context->tenant();
        $tenant->update($request->validated());

        return response()->json(['data' => $tenant->fresh()]);
    }

    public function members(): JsonResponse
    {
        $tenant = $this->context->tenant();

        $members = $tenant->users()
            ->with(['branches' => fn ($query) => $query
                ->where('branches.tenant_id', $tenant->id)
                ->where('branches.is_active', true)
                ->orderBy('branches.name')])
            ->orderBy('name')
            ->get();

        $members->each(function (User $member): void {
            $member->setAttribute('branch_ids', $member->branches->pluck('id')->map(fn ($id): int => (int) $id)->values());
        });

        return response()->json(['data' => $members]);
    }

    public function storeMember(StoreMemberRequest $request): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);

        $tenant = $this->context->tenant();
        $email = strtolower(trim((string) $request->validated('email')));
        $role = (string) $request->validated('role');
        $branchIds = array_map('intval', $request->validated('branch_ids'));

        abort_if($role === 'admin' && ! $this->authorizer->canManageUsers($request->user()), 403);

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $isNew = ! $user instanceof User;

        if ($user instanceof User) {
            $membership = $user->tenants()->whereKey($tenant->id)->first();

            abort_if(
                $membership !== null && (bool) $membership->pivot->is_active,
                422,
                'This user is already a member of this account.',
            );

            $belongsToAnotherAccount = $user->tenants()
                ->whereKeyNot($tenant->id)
                ->wherePivot('is_active', true)
                ->exists();

            abort_if($belongsToAnotherAccount, 422, 'This email already belongs to another account.');

            $user->update(['name' => $request->validated('name')]);
        } else {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $email,
                'password' => Str::random(48),
            ]);
        }

        DB::transaction(function () use ($tenant, $user, $role, $branchIds): void {
            $tenant->users()->syncWithoutDetaching([
                $user->id => ['role' => $role, 'is_active' => true],
            ]);

            $this->syncTenantBranches($tenant, $user, $branchIds);
        });

        if ($isNew) {
            $this->memberInvitations->invite($user, $tenant);
        } else {
            $user->notify(new MemberAddedNotification($tenant));
        }

        $user->load(['branches' => fn ($query) => $query
            ->where('branches.tenant_id', $tenant->id)
            ->where('branches.is_active', true)
            ->orderBy('branches.name')]);
        $user->setAttribute('branch_ids', $user->branches->pluck('id')->map(fn ($id): int => (int) $id)->values());

        return response()->json(['data' => $user], 201);
    }

    public function updateMember(UpdateMembershipRequest $request, User $user): JsonResponse
    {
        abort_unless($this->authorizer->canManageUsers($request->user()), 403);
        abort_unless($this->context->tenant()->users()->whereKey($user->id)->exists(), 404);
        abort_unless($user->id !== $request->user()->id, 422, 'You cannot change your own membership here.');

        $role = (string) $request->validated('role');

        if ($role === 'member') {
            $activeBranches = $user->branches()
                ->where('branches.tenant_id', $this->context->tenantId())
                ->where('branches.is_active', true)
                ->count();

            abort_if($activeBranches === 0, 422, 'Assign at least one branch before making this user a member.');
        }

        $this->context->tenant()->users()->updateExistingPivot($user->id, $request->validated());

        return response()->json(['data' => $user->fresh()]);
    }

    public function updateMemberBranches(UpdateMemberBranchesRequest $request, User $user): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);
        abort_unless($this->context->tenant()->users()->whereKey($user->id)->exists(), 404);

        $branchIds = array_map('intval', $request->validated('branch_ids'));

        DB::transaction(fn () => $this->syncTenantBranches($this->context->tenant(), $user, $branchIds));

        $user->load(['branches' => fn ($query) => $query
            ->where('branches.tenant_id', $this->context->tenantId())
            ->where('branches.is_active', true)
            ->orderBy('branches.name')]);
        $user->setAttribute('branch_ids', $user->branches->pluck('id')->map(fn ($id): int => (int) $id)->values());

        return response()->json(['data' => $user]);
    }

    public function resendInvite(Request $request, User $user): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);
        abort_unless($this->context->tenant()->users()->whereKey($user->id)->exists(), 404);

        $this->memberInvitations->invite($user, $this->context->tenant());

        return response()->json(['message' => 'Invite sent.']);
    }

    /** @param  array<int, int>  $branchIds */
    private function syncTenantBranches(Tenant $tenant, User $user, array $branchIds): void
    {
        $tenantBranchIds = $tenant->branches()->pluck('id')->map(fn ($id): int => (int) $id);

        DB::table('branch_user')
            ->where('user_id', $user->id)
            ->whereIn('branch_id', $tenantBranchIds->all())
            ->whereNotIn('branch_id', $branchIds)
            ->delete();

        $user->branches()->syncWithoutDetaching($branchIds);
    }
}
