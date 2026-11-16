<?php

namespace App\Services\Stock;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\TransactionLine;
use App\Support\Service;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\TenantContext;

/**
 * Derives every stock figure from the transaction ledger.
 *
 * Purchases add to stock and sales subtract from it, so the position of an item
 * is always the sum of its lines. Nothing is cached in a column, which is why
 * deleting a line needs no reconciliation step.
 */
class StockLedger extends Service
{
    public function __construct(private readonly TenantContext $context) {}
    /**
     * Stock positions for every item in the given collection, or the whole
     * catalogue when omitted. Items that have never traded are included with
     * zeroed figures so the stock table can list them.
     *
     * @param  Collection<int, Item>|null  $items
     * @return Collection<int, ItemPosition> keyed by item id, sorted by name
     */
    public function positions(?Collection $items = null): Collection
    {
        $items ??= Item::where('tenant_id', $this->context->tenantId())->get();

        return $this->buildPositions($items, $this->aggregate());
    }

    public function positionsForAccount(?Collection $items = null): Collection
    {
        $items ??= Item::where('tenant_id', $this->context->tenantId())->get();

        return $this->buildPositions($items, $this->aggregate(null, true));
    }

    public function positionsForBranch(Collection $items, int $branchId): Collection
    {
        $items = $items instanceof EloquentCollection ? $items : new EloquentCollection($items->all());

        return $this->buildPositions($items, $this->aggregate($branchId));
    }

    public function positionsForBranchWithActivity(Collection $items, int $branchId): Collection
    {
        $positions = $this->positionsForBranch($items, $branchId);
        $activeIds = DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.tenant_id', $this->context->tenantId())
            ->where('transactions.branch_id', $branchId)
            ->pluck('transaction_lines.item_id')
            ->merge(DB::table('stock_transfer_lines')
                ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_lines.stock_transfer_id')
                ->where('stock_transfers.tenant_id', $this->context->tenantId())
                ->where('stock_transfers.status', 'completed')
                ->where(fn ($query) => $query
                    ->where('stock_transfers.source_branch_id', $branchId)
                    ->orWhere('stock_transfers.destination_branch_id', $branchId))
                ->pluck('stock_transfer_lines.item_id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        return $positions->filter(fn (ItemPosition $position) => $activeIds->contains($position->item->id));
    }

    private function buildPositions(Collection $items, array $totals): Collection
    {
        $this->loadDisplayUnits($items);

        // sortBy() keeps the keys, which callers rely on to look a position up
        // by item id. Do not reindex with values() here.
        return $items
            ->mapWithKeys(function (Item $item) use ($totals) {
                $row = $totals[$item->id] ?? ['bought' => 0, 'cost' => 0, 'sold' => 0, 'revenue' => 0];

                return [$item->id => new ItemPosition(
                    item: $item,
                    bought: (float) $row['bought'],
                    cost: (float) $row['cost'],
                    sold: (float) $row['sold'],
                    revenue: (float) $row['revenue'],
                    displayUnit: $item->displayUnit,
                )];
            })
            ->sortBy(fn (ItemPosition $position) => mb_strtolower($position->item->name));
    }

    /** Positions for every item that has at least one ledger line. */
    public function tradedPositions(): Collection
    {
        $itemIds = TransactionLine::query()
            ->whereHas('transaction', fn ($query) => $query->where('tenant_id', $this->context->tenantId()))
            ->distinct()
            ->pluck('item_id');

        return $this->positions(Item::with('displayUnit')->whereIn('id', $itemIds)->get());
    }

    /** The position of a single item, recomputed from the ledger. */
    public function position(Item $item): ItemPosition
    {
        $item->loadMissing('displayUnit');

        return $this->positions(new EloquentCollection([$item]))->get($item->id);
    }

    /**
     * Accepts either an Eloquent collection, which can eager load in one query,
     * or a plain collection, where each model loads its own unit.
     *
     * @param  Collection<int, Item>  $items
     */
    private function loadDisplayUnits(Collection $items): void
    {
        if ($items instanceof EloquentCollection) {
            $items->loadMissing('displayUnit');

            return;
        }

        $items->each(fn (Item $item) => $item->loadMissing('displayUnit'));
    }

    /** Base units currently available, before any pending cart lines. */
    public function stockFor(Item $item): float
    {
        return $this->position($item)->stock();
    }

    public function tolerance(): float
    {
        return $this->toleranceValue();
    }

    private function toleranceValue(): float
    {
        return 1e-9;
    }

    public function totalStockValue(?Collection $positions = null): float
    {
        return ($positions ?? $this->tradedPositions())
            ->sum(fn (ItemPosition $position) => $position->stockValue());
    }

    public function totalPurchased(?Collection $positions = null): float
    {
        return ($positions ?? $this->tradedPositions())
            ->sum(fn (ItemPosition $position) => $position->cost);
    }

    public function totalStockValueForBranch(int $branchId): float
    {
        $items = Item::where('tenant_id', $this->context->tenantId())->get();

        return $this->totalStockValue($this->positionsForBranch($items, $branchId));
    }

    public function totalStockValueForAccount(): float
    {
        return $this->totalStockValue($this->positionsForAccount());
    }

    /**
     * One row per item, summing lines by direction.
     *
     * @return array<int, array{bought: float, cost: float, sold: float, revenue: float}>
     */
    private function aggregate(?int $branchId = null, bool $account = false): array
    {
        $purchase = RecordType::Purchase->value;
        $sale = RecordType::Sale->value;
        $branchId = $account ? null : ($branchId ?? ($this->context->branchRequired() ? $this->context->branchId() : null));

        $query = DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.tenant_id', $this->context->tenantId());

        if ($branchId !== null) {
            $query->where('transactions.branch_id', $branchId);
        }

        $rows = $query
            ->selectRaw('transaction_lines.item_id as item_id')
            ->selectRaw('sum(case when transactions.type = ? then transaction_lines.base_qty else 0 end) as bought', [$purchase])
            ->selectRaw('sum(case when transactions.type = ? then COALESCE(transaction_lines.unit_cost, transaction_lines.unit_price) * transaction_lines.qty else 0 end) as cost', [$purchase])
            ->selectRaw('sum(case when transactions.type = ? then transaction_lines.base_qty else 0 end) as sold', [$sale])
            ->selectRaw('sum(case when transactions.type = ? then transaction_lines.total else 0 end) as revenue', [$sale])
            ->groupBy('transaction_lines.item_id')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->item_id] = [
                'bought' => (float) $row->bought,
                'cost' => (float) $row->cost,
                'sold' => (float) $row->sold,
                'revenue' => (float) $row->revenue,
            ];
        }

        if ($branchId !== null) {
            $transferRows = DB::table('stock_transfer_lines')
                ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_lines.stock_transfer_id')
                ->where('stock_transfers.tenant_id', $this->context->tenantId())
                ->where('stock_transfers.status', 'completed')
                ->where(function ($q) use ($branchId) {
                    $q->where('stock_transfers.source_branch_id', $branchId)
                        ->orWhere('stock_transfers.destination_branch_id', $branchId);
                })
                ->selectRaw('stock_transfer_lines.item_id as item_id')
                ->selectRaw('sum(case when stock_transfers.destination_branch_id = ? then stock_transfer_lines.base_qty else 0 end) as transfer_in', [$branchId])
                ->selectRaw('sum(case when stock_transfers.source_branch_id = ? then stock_transfer_lines.base_qty else 0 end) as transfer_out', [$branchId])
                ->selectRaw('sum(case when stock_transfers.destination_branch_id = ? then COALESCE(stock_transfer_lines.total_cost, 0) else 0 end) as transfer_in_cost', [$branchId])
                ->groupBy('stock_transfer_lines.item_id')
                ->get();

            foreach ($transferRows as $transfer) {
                $id = (int) $transfer->item_id;
                $totals[$id] ??= ['bought' => 0.0, 'cost' => 0.0, 'sold' => 0.0, 'revenue' => 0.0];
                $totals[$id]['bought'] += (float) $transfer->transfer_in;
                $totals[$id]['sold'] += (float) $transfer->transfer_out;
                $totals[$id]['cost'] += (float) $transfer->transfer_in_cost;
            }

            // Internal transfer purchases are display records; their stock is
            // already represented by the transfer rows above.
            $totals = $this->removeInternalTransferPurchases($totals, $branchId);
        }

        return $totals;
    }

    private function removeInternalTransferPurchases(array $totals, int $branchId): array
    {
        $rows = DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.tenant_id', $this->context->tenantId())
            ->where('transactions.branch_id', $branchId)
            ->where('transactions.type', RecordType::Purchase->value)
            ->whereNotNull('transactions.stock_transfer_id')
            ->selectRaw('transaction_lines.item_id as item_id')
            ->selectRaw('sum(transaction_lines.base_qty) as bought')
            ->selectRaw('sum(COALESCE(transaction_lines.unit_cost, transaction_lines.unit_price) * transaction_lines.qty) as cost')
            ->groupBy('transaction_lines.item_id')
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->item_id;
            if (! isset($totals[$id])) {
                continue;
            }
            $totals[$id]['bought'] -= (float) $row->bought;
            $totals[$id]['cost'] -= (float) $row->cost;
        }

        return $totals;
    }
}
