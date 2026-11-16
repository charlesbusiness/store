<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreItemRequest;
use App\Http\Requests\Api\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Services\Stock\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Support\TenantContext;

class ItemController extends Controller
{
    public function __construct(private readonly StockLedger $ledger, private readonly TenantContext $context) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = Item::with(['unit'])->where('tenant_id', $this->context->tenantId())->search($request->query('q'))->alphabetical();

        if ($request->boolean('with_stock')) {
            $items = $query->get();
            $positions = $this->ledger->positionsForBranch($items, $this->context->requireBranch());
            
            return response()->json([
                'data' => $items->map(function ($item) use ($positions) {
                    $position = $positions->get($item->id);
                    return array_merge(
                        (new ItemResource($item))->resolve(),
                        $position ? [
                            'stock' => $position->stock(),
                            'stockLabel' => $position->stockLabel(),
                            'costPerUnit' => $position->costPerDisplayUnit(),
                            'totalValue' => $position->stockValue(),
                            'lastSellingPrice' => $item->last_selling_price ?? 0,
                        ] : [
                            'stock' => 0,
                            'stockLabel' => '0',
                            'costPerUnit' => 0,
                            'totalValue' => 0,
                            'lastSellingPrice' => 0,
                        ]
                    );
                })->values()
            ]);
        }

        $items = $query->paginate(20);
        return ItemResource::collection($items);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['name'] = Item::normaliseName($data['name']);
        $data['name_key'] = Item::nameKey($data['name']);
        $data['tenant_id'] = $this->context->tenantId();

        $item = Item::create($data);
        return response()->json(['data' => new ItemResource($item)], 201);
    }

    public function show(Item $item): JsonResponse
    {
        $this->assertOwned($item);
        $item->load(['unit', 'packs', 'displayUnit']);
        $position = $this->ledger->position($item);
        
        return response()->json([
            'data' => array_merge(
                (new ItemResource($item))->resolve(),
                [
                    'stock' => $position->stock(),
                    'stockLabel' => $position->stockLabel(),
                    'costPerUnit' => $position->costPerDisplayUnit(),
                    'totalValue' => $position->stockValue(),
                    'lastSellingPrice' => $item->last_selling_price ?? 0,
                ]
            )
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        $this->assertOwned($item);

        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $data['name'] = Item::normaliseName($data['name']);
            $data['name_key'] = Item::nameKey($data['name']);
        }

        $item->update($data);

        return response()->json(['data' => new ItemResource($item)]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->assertOwned($item);
        $item->delete();

        return response()->json(['message' => 'Item deleted']);
    }

    private function assertOwned(Item $item): void
    {
        abort_unless($item->tenant_id === $this->context->tenantId(), 404);
    }
}
