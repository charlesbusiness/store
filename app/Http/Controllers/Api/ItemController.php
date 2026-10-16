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

class ItemController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = Item::with(['unit'])->search($request->query('q'))->alphabetical();

        if ($request->boolean('with_stock')) {
            $items = $query->get();
            $positions = $this->ledger->positions($items);
            
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
        $data['name_key'] = strtolower($data['name']);
        
        $item = Item::create($data);
        return response()->json(['data' => new ItemResource($item)], 201);
    }

    public function show(Item $item): JsonResponse
    {
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
        $item->update($request->validated());
        return response()->json(['data' => new ItemResource($item)]);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();
        return response()->json(['message' => 'Item deleted']);
    }
}
