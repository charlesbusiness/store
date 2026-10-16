<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUnitRequest;
use App\Http\Requests\Api\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\Stock\UnitCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function __construct(private readonly UnitCatalog $catalog) {}

    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        if ($request->boolean('grouped')) {
            return response()->json(['data' => $this->catalog->groupedCatalogue()]);
        }

        $units = Unit::latest()->paginate(20);
        return UnitResource::collection($units);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());
        return response()->json(['data' => new UnitResource($unit)], 201);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json(['data' => new UnitResource($unit)]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());
        return response()->json(['data' => new UnitResource($unit)]);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $unit->update(['is_active' => false]);
        return response()->json(['message' => 'Unit disabled']);
    }
}
