<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAuthorizer;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantAuthorizer $authorizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $tenantId = (int) ($request->header('X-Tenant-ID') ?: $user->tenants()->wherePivot('is_active', true)->value('tenants.id'));
        $tenant = $user->tenants()->whereKey($tenantId)->wherePivot('is_active', true)->first();

        abort_unless($tenant instanceof Tenant, 403, 'You do not have access to this account.');

        $allowed = $this->authorizer->allowedBranches($user, $tenant);
        $branchId = $request->header('X-Branch-ID');

        if ($branchId) {
            $branch = $allowed->firstWhere('id', (int) $branchId);
            abort_unless($branch instanceof Branch, 403, 'Choose a branch you can access.');
        } else {
            $branch = $allowed->firstWhere('is_default', true) ?? $allowed->first();
            abort_unless($branch instanceof Branch, 403, 'You have not been assigned to a branch.');
        }

        $this->context->set($tenant, $branch);

        return $next($request);
    }
}
