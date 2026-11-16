<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;
    private ?Branch $branch = null;

    public function set(Tenant $tenant, ?Branch $branch = null): void
    {
        $this->tenant = $tenant;
        $this->branch = $branch;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new \LogicException('Tenant context has not been initialized.');
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function tenantId(): int
    {
        return $this->tenant()->id;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }

    public function branchRequired(): bool
    {
        return $this->tenant()->stock_management_mode->value === 'branch';
    }

    public function requireBranch(): int
    {
        return $this->branch?->id ?? throw new \LogicException('A branch must be selected.');
    }
}
