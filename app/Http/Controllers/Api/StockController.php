<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Stock\PackReader;
use App\Services\Stock\StockLedger;
use App\Models\Item;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly PackReader $packs,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = Item::where('tenant_id', $this->context->tenantId())->get();
        $branchId = $this->context->requireBranch();
        $positions = $request->boolean('traded_only')
            ? $this->ledger->positionsForBranchWithActivity($items, $branchId)
            : $this->ledger->positionsForBranch($items, $branchId);

        if ($request->boolean('traded_only')) {
            $positions = $positions->filter(fn ($position) => $position->hasTraded() || $position->stock() != 0);
        }

        $data = $positions->map(function ($position) {
            return [
                'id' => $position->item->id,
                'name' => $position->item->name,
                'family' => $position->item->family->value,
                'unit' => $position->item->default_unit_code,
                'stock' => $position->stock(),
                'stockLabel' => $position->stockLabel(),
                'costPerUnit' => $position->costPerDisplayUnit(),
                'totalValue' => $position->stockValue(),
                'lastSellingPrice' => $position->item->last_selling_price ?? 0,
            ];
        });

        $breakdowns = $this->packs->breakdownsFor($positions);
        $traded = $positions->filter(fn ($p) => $p->hasTraded());
        $stockValue = $this->ledger->totalStockValue($positions);
        $totalPurchased = $this->ledger->totalPurchased($positions);

        return response()->json([
            'data' => $data->values(),
            'breakdowns' => $breakdowns,
            'traded' => $traded->values(),
            'stockValue' => $stockValue,
            'totalPurchased' => $totalPurchased,
        ]);
    }
}
