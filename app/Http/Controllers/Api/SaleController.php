<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecordType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordTransactionRequest;
use App\Http\Requests\UpdateSaleLineRequest;
use App\Http\Resources\SaleResource;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Services\Record\LineNormalizer;
use App\Services\Record\RecordFormData;
use App\Services\Record\SaleRecorder;
use App\Services\Stock\ActivityReader;
use App\Services\Stock\AvailabilityChecker;
use App\Support\Concerns\FormatsValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    use FormatsValues;

    public function __construct(
        private readonly RecordFormData $formData,
        private readonly ActivityReader $activity,
        private readonly AvailabilityChecker $availability,
    ) {}

    /**
     * The line-item builder's configuration, identical to window.RecordForm on
     * the server-rendered sell page: only what is in stock, each carrying the
     * cost, the declared packs and the price it last went for.
     */
    public function form(): JsonResponse
    {
        return response()->json(['data' => $this->formData->formConfig(RecordType::Sale)]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->query('status', $request->query('payment_status'));
        $paid = null;
        if (is_string($status)) {
            $status = strtolower(trim($status));
            if ($status === 'paid') {
                $paid = true;
            } elseif ($status === 'pending') {
                $paid = false;
            } elseif ($status === 'all' || $status === '') {
                $paid = null;
            }
        } elseif ($request->has('paid')) {
            $paidVal = $request->query('paid');
            if ($paidVal === 'true' || $paidVal === '1') {
                $paid = true;
            } elseif ($paidVal === 'false' || $paidVal === '0') {
                $paid = false;
            } else {
                $paid = null;
            }
        }

        $sales = $this->activity->paginateFiltered(
            RecordType::Sale,
            $request->query('search'),
            $request->query('date_from'),
            $request->query('date_to'),
            (int) $request->query('per_page', 20),
            $paid,
        );

        return SaleResource::collection($sales);
    }

    /**
     * Records a sale through the same writer the blade sell form uses, so the
     * stock actually drawn is guarded and the cost is snapshotted on write
     * rather than left blank.
     */
    public function store(RecordTransactionRequest $request): JsonResponse
    {
        $sale = app(SaleRecorder::class)->record($request->payload(), $request->user());
        $sale->load('lines.item', 'lines.unit');

        return response()->json([
            'data' => new SaleResource($sale),
            'message' => sprintf(
                '%s of %s recorded for %s.',
                RecordType::Sale->label(),
                self::money($sale->total()),
                $sale->transacted_on->toFormattedDateString(),
            ),
        ], 201);
    }

    public function show(Transaction $sale): JsonResponse
    {
        $sale->load(['lines.item', 'lines.unit']);

        return response()->json(['data' => new SaleResource($sale)]);
    }

    public function destroy(Transaction $sale): JsonResponse
    {
        $sale->delete();

        return response()->json(['message' => 'Sale deleted']);
    }

    public function destroyLine(Transaction $sale, TransactionLine $line): JsonResponse
    {
        if ($line->transaction_id !== $sale->id) {
            return response()->json(['message' => 'Line not found in this sale'], 404);
        }

        DB::transaction(function () use ($line, $sale) {
            $line->delete();

            if ($sale->lines()->doesntExist()) {
                $sale->delete();
            }
        });

        return response()->json(['message' => 'Line deleted']);
    }

    public function markPaid(Transaction $sale): JsonResponse
    {
        $sale->update(['paid' => true]);

        return response()->json(['message' => 'Sale marked as paid']);
    }

    /**
     * Correct the quantity or selling price on a line. The item, unit and
     * declared packs are fixed once written, so they are read from the stored
     * line rather than the request; only qty and the selling price can move.
     *
     * Unlike a purchase, a sale draws on stock that already exists, so the
     * corrected quantity is re-checked against what is available (adding back
     * the line's own draw, which the ledger already subtracted).
     */
    public function updateLine(UpdateSaleLineRequest $request, Transaction $sale, TransactionLine $line): JsonResponse
    {
        if ($line->transaction_id !== $sale->id) {
            return response()->json(['message' => 'Line not found in this sale'], 404);
        }

        DB::transaction(function () use ($request, $line) {
            $line->loadMissing('item');
            Item::whereKey($line->item_id)->lockForUpdate()->get();

            $item = $line->item;

            $row = [
                'item_id' => $line->item_id,
                'qty' => $request->validated('qty'),
                'unit_code' => $line->unit_code,
                'unit_price' => $request->validated('unit_price'),
                'unit_cost' => $line->unit_cost,
                'inner_units' => $line->inner_units ?? [],
            ];

            $normalised = app(LineNormalizer::class)
                ->normalise(RecordType::Sale, [$row], collect([$line->item_id => $item]))
                ->first();

            $available = $this->availability->available($item) + (float) $line->base_qty;

            if ($normalised['base_qty'] > $available + $this->availability->tolerance()) {
                throw ValidationException::withMessages([
                    'lines' => 'Not enough stock to increase this line.',
                ]);
            }

            $oldQty = (float) $line->qty;

            $line->update([
                'qty' => $normalised['qty'],
                'base_qty' => $normalised['base_qty'],
                'unit_price' => $normalised['unit_price'],
                'total' => $normalised['total'],
                'selling_price' => $normalised['selling_price'],
                // The cost is a snapshot of what the goods were bought for.
                // Scale it with the quantity so the average is preserved;
                // editing a selling price alone must not reprice the cost.
                'cost' => $line->cost === null || $oldQty <= 0
                    ? $line->cost
                    : round($line->cost * $normalised['qty'] / $oldQty, 2),
            ]);
        });

        $sale->load(['lines.item', 'lines.unit']);

        return response()->json([
            'data' => new SaleResource($sale),
            'message' => 'Line updated.',
        ]);
    }
}
