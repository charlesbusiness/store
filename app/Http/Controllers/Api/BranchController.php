<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBranchRequest;
use App\Http\Requests\Api\UpdateBranchRequest;
use App\Models\Branch;
use App\Support\TenantAuthorizer;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantAuthorizer $authorizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->authorizer->allowedBranches($request->user(), $this->context->tenant()),
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);
        $branch = $this->context->tenant()->branches()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'is_default' => false,
            'is_active' => true,
        ]);
        $branch->users()->attach($request->user()->id);

        return response()->json(['data' => $branch], 201);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);
        $this->assertBranch($branch);
        $branch->update($request->validated());

        return response()->json(['data' => $branch->fresh()]);
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        abort_unless($this->authorizer->canManage($request->user()), 403);
        $this->assertBranch($branch);
        abort_if($branch->is_default, 422, 'The default branch cannot be disabled.');

        $tenant = $this->context->tenant();

        foreach ($branch->users()->get() as $user) {
            $role = DB::table('tenant_user')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->value('role');

            if ($role !== 'member') {
                continue;
            }

            $activeBranches = DB::table('branch_user')
                ->join('branches', 'branches.id', '=', 'branch_user.branch_id')
                ->where('branch_user.user_id', $user->id)
                ->where('branches.tenant_id', $tenant->id)
                ->where('branches.is_active', true)
                ->count();

            abort_if(
                $activeBranches <= 1,
                422,
                "Assign {$user->name} to another branch before disabling this one.",
            );
        }

        DB::transaction(function () use ($branch): void {
            $branch->update(['is_active' => false]);
            DB::table('branch_user')->where('branch_id', $branch->id)->delete();
        });

        return response()->json(['message' => 'Branch disabled.']);
    }

    private function assertBranch(Branch $branch): void
    {
        abort_unless($branch->tenant_id === $this->context->tenantId(), 404);
    }
}
