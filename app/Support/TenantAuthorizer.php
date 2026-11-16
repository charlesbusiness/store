<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TenantAuthorizer
{
    public function __construct(private readonly TenantContext $context) {}

    public function roleFor(User $user, Tenant $tenant): string
    {
        return (string) $user->tenants()
            ->whereKey($tenant->id)
            ->wherePivot('is_active', true)
            ->firstOrFail()
            ->pivot
            ->role;
    }

    public function role(User $user): string
    {
        return $this->roleFor($user, $this->context->tenant());
    }

    public function canManage(User $user): bool
    {
        return in_array($this->role($user), ['owner', 'admin'], true);
    }

    public function canManageUsers(User $user): bool
    {
        return $this->role($user) === 'owner';
    }

    /**
     * The branches this user may work in for the tenant.
     *
     * Owners and admins reach every active branch; everyone else reaches only
     * the branches they have been assigned to.
     *
     * @return Collection<int, Branch>
     */
    public function allowedBranches(User $user, Tenant $tenant): Collection
    {
        if (in_array($this->roleFor($user, $tenant), ['owner', 'admin'], true)) {
            return $tenant->branches()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return $user->branches()
            ->where('branches.tenant_id', $tenant->id)
            ->where('branches.is_active', true)
            ->orderBy('branches.name')
            ->get();
    }

    /** @return array<int, int> */
    public function allowedBranchIds(User $user, Tenant $tenant): array
    {
        return $this->allowedBranches($user, $tenant)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function canAccessBranch(User $user, Branch $branch): bool
    {
        if ($branch->tenant_id !== $this->context->tenantId()) {
            return false;
        }

        return $this->allowedBranches($user, $this->context->tenant())
            ->contains(fn (Branch $allowed): bool => $allowed->id === $branch->id);
    }
}
