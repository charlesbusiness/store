<?php

namespace App\Services\Stock;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\TransactionLine;
use App\Support\Service;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Derives every stock figure from the transaction ledger.
 *
 * Purchases add to stock and sales subtract from it, so the position of an item
 * is always the sum of its lines. Nothing is cached in a column, which is why
 * deleting a line needs no reconciliation step.
 */
class StockLedger extends Service
{
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
        $items ??= Item::all();
        $this->loadDisplayUnits($items);

        $totals = $this->aggregate();

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

    /**
     * One row per item, summing lines by direction.
     *
     * @return array<int, array{bought: float, cost: float, sold: float, revenue: float}>
     */
    private function aggregate(): array
    {
        $purchase = RecordType::Purchase->value;
        $sale = RecordType::Sale->value;

        $rows = DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
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

        return $totals;
    }
}
