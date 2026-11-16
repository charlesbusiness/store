<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\Stock\StockTransferService;
use App\Support\TenantAuthorizer;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantAuthorizer $authorizer,
        private readonly StockTransferService $transfers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::query()
            ->where('tenant_id', $this->context->tenantId())
            ->with(['sourceBranch', 'destinationBranch', 'lines.item', 'lines.unit'])
            ->latest('transferred_on')
            ->latest('id');

        if ($this->authorizer->role($request->user()) === 'member') {
            $branchIds = $this->authorizer->allowedBranchIds($request->user(), $this->context->tenant());

            $query->where(function ($builder) use ($branchIds): void {
                $builder->whereIn('source_branch_id', $branchIds)
                    ->orWhereIn('destination_branch_id', $branchIds);
            });
        }

        $transfers = $query->paginate((int) $request->query('per_page', 20));

        return response()->json($transfers);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->transfers->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $transfer, 'message' => 'Stock transferred.'], 201);
    }
}
