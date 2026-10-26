<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecordType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordTransactionRequest;
use App\Http\Requests\UpdatePurchaseLineRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Services\Record\LineNormalizer;
use App\Services\Record\PurchaseRecorder;
use App\Services\Record\RecordFormData;
use App\Services\Stock\ActivityReader;
use App\Support\Concerns\FormatsValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    use FormatsValues;

    public function __construct(
        private readonly RecordFormData $formData,
        private readonly ActivityReader $activity,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $purchases = $this->activity->paginateFiltered(
            RecordType::Purchase,
            $request->query('search'),
            $request->query('date_from'),
            $request->query('date_to'),
        );

        return PurchaseResource::collection($purchases);
    }

    /**
     * The line-item builder's configuration for the buy form.
     */
    public function form(): JsonResponse
    {
        return response()->json(['data' => $this->formData->formConfig(RecordType::Purchase)]);
    }

    /**
     * Records a purchase through the shared recorder, so a name nobody has
     * bought before creates the item rather than being refused.
     */
    public function store(RecordTransactionRequest $request): JsonResponse
    {
        $purchase = app(PurchaseRecorder::class)->record($request->payload(), $request->user());
        $purchase->load('lines.item', 'lines.unit');

        return response()->json([
            'data' => new PurchaseResource($purchase),
            'message' => sprintf(
                '%s of %s recorded for %s.',
                RecordType::Purchase->label(),
                self::money($purchase->total()),
                $purchase->transacted_on->toFormattedDateString(),
            ),
        ], 201);
    }

    public function show(Transaction $purchase): JsonResponse
    {
        $purchase->load(['lines.item', 'lines.unit']);
        return response()->json(['data' => new PurchaseResource($purchase)]);
    }

    public function destroy(Transaction $purchase): JsonResponse
    {
        $purchase->delete();
        return response()->json(['message' => 'Purchase deleted']);
    }

    public function destroyLine(Transaction $purchase, TransactionLine $line): JsonResponse
    {
        if ($line->transaction_id !== $purchase->id) {
            return response()->json(['message' => 'Line not found in this purchase'], 404);
        }

        DB::transaction(function () use ($line, $purchase) {
            $line->delete();

            if ($purchase->lines()->doesntExist()) {
                $purchase->delete();
            }
        });

        return response()->json(['message' => 'Line deleted']);
    }

    /**
     * Correct the quantity or prices on a line. The item, unit and declared
     * packs are fixed once written, so they are read from the stored line
     * rather than the request; only qty, cost and selling price can move.
     */
    public function updateLine(UpdatePurchaseLineRequest $request, Transaction $purchase, TransactionLine $line): JsonResponse
    {
        if ($line->transaction_id !== $purchase->id) {
            return response()->json(['message' => 'Line not found in this purchase'], 404);
        }

        DB::transaction(function () use ($request, $purchase, $line) {
            $line->loadMissing('item');
            Item::whereKey($line->item_id)->lockForUpdate()->get();

            $row = [
                'item_id' => $line->item_id,
                'qty' => $request->validated('qty'),
                'unit_code' => $line->unit_code,
                'unit_cost' => $request->validated('unit_cost'),
                'selling_price' => $request->validated('selling_price'),
                'inner_units' => $line->inner_units ?? [],
            ];

            $normalised = app(LineNormalizer::class)
                ->normalise(RecordType::Purchase, [$row], collect([$line->item_id => $line->item]))
                ->first();

            $line->update([
                'qty' => $normalised['qty'],
                'base_qty' => $normalised['base_qty'],
                'unit_price' => $normalised['unit_price'],
                'total' => $normalised['total'],
                'unit_cost' => $normalised['unit_cost'],
                'selling_price' => $normalised['selling_price'],
            ]);
        });

        $purchase->load(['lines.item', 'lines.unit']);

        return response()->json([
            'data' => new PurchaseResource($purchase),
            'message' => 'Line updated.',
        ]);
    }
}
