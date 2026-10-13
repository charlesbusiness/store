<?php

namespace App\Services\Stock;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\Transaction;
use App\Support\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the ledger as a timeline: the most recent transactions of a type, and
 * the price an item last went for.
 */
class ActivityReader extends Service
{
    public function __construct(private readonly UnitCatalog $units) {}

    /** How many transactions of a type exist, for the "showing N of M" hint. */
    public function transactionsOf(RecordType $type): int
    {
        return Transaction::query()->where('type', $type->value)->count();
    }

    /**
     * Total transactions matching filters (for accurate "showing X of Y" count).
     */
    public function transactionsFiltered(
        RecordType $type,
        ?string $search = null,
        ?CarbonImmutable $dateFrom = null,
        ?CarbonImmutable $dateTo = null
    ): int {
        return $this->applyFilters(
            Transaction::query()->where('type', $type->value),
            $search, $dateFrom, $dateTo
        )->count();
    }

    /**
     * The most recent transactions of one type, newest first, with their lines.
     *
     * Grouped rather than flat because the question a sale page is really asking
     * is "what did that sale make me", which no single line can answer. The limit
     * counts transactions, not lines, so a subtotal is never shown for a sale
     * whose lines have been cut off by it.
     *
     * @return EloquentCollection<int, Transaction>
     */
    public function recentGrouped(RecordType $type, ?int $limit = null, int $offset = 0): EloquentCollection
    {
        $limit ??= (int) config('stockly.recent_limit', 8);

        return Transaction::query()
            ->where('type', $type->value)
            ->with(['lines.item.displayUnit', 'lines.unit'])
            ->orderByDesc('transacted_on')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Filtered recent transactions for the buy page.
     *
     * Filters:
     * - search: item name LIKE (case-insensitive)
     * - dateFrom: transaction date >= dateFrom
     * - dateTo: transaction date <= dateTo
     *
     * @return EloquentCollection<int, Transaction>
     */
    public function recentGroupedFiltered(
        RecordType $type,
        ?int $limit = null,
        int $offset = 0,
        ?string $search = null,
        ?CarbonImmutable $dateFrom = null,
        ?CarbonImmutable $dateTo = null
    ): EloquentCollection
    {
        $limit ??= (int) config('stockly.recent_limit', 8);

        return $this->applyFilters(
            Transaction::query()->where('type', $type->value),
            $search, $dateFrom, $dateTo
        )
            ->with(['lines.item.displayUnit', 'lines.unit'])
            ->orderByDesc('transacted_on')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Apply search and date filters to a transaction query.
     */
    private function applyFilters(
        $query,
        ?string $search,
        ?CarbonImmutable $dateFrom,
        ?CarbonImmutable $dateTo
    ) {
        if ($search !== null && $search !== '') {
            $query->whereHas('lines.item', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        if ($dateFrom !== null) {
            $query->whereDate('transacted_on', '>=', $dateFrom->toDateString());
        }

        if ($dateTo !== null) {
            $query->whereDate('transacted_on', '<=', $dateTo->toDateString());
        }

        return $query;
    }

    /**
     * The price each item last sold for, restated in that item's own trading
     * unit. Restating matters: the last sale may have been in halves while the
     * form defaults to bags, and pre-filling a per-bag box with a per-half
     * figure would be worse than leaving it blank.
     *
     * @param  Collection<int, Item>  $items
     * @return array<int, float> item id => price per trading unit
     */
    public function lastSalePrices(Collection $items): array
    {
        $this->loadDisplayUnits($items);
        $ids = $items->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        // Ordered newest first, so the first row seen for an item is its last sale.
        $rows = DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.type', RecordType::Sale->value)
            ->whereIn('transaction_lines.item_id', $ids)
            ->orderByDesc('transactions.transacted_on')
            ->orderByDesc('transaction_lines.id')
            ->get([
                'transaction_lines.item_id as item_id',
                'transaction_lines.unit_code as unit_code',
                'transaction_lines.base_qty as base_qty',
                'transaction_lines.unit_price as unit_price',
            ]);

        $latest = [];

        foreach ($rows as $row) {
            $latest[(int) $row->item_id] ??= $row;
        }

        $prices = [];

        foreach ($items as $item) {
            $row = $latest[$item->id] ?? null;

            if (! $row || $item->displayUnit === null) {
                continue;
            }

            $soldUnit = $this->units->find($row->unit_code);

            // A price in another family is not comparable, so skip it.
            if (! $soldUnit || ! $this->sharesFamily($soldUnit, $item->displayUnit)) {
                continue;
            }

            if ($row->base_qty > 0) {
                $perBaseUnit = $row->unit_price / $this->toBase(1, $soldUnit);
                $prices[$item->id] = $perBaseUnit * $item->displayUnit->base_multiplier;
            }
        }

        return $prices;
    }

    /**
     * loadMissing() only exists on Eloquent collections, and these items often
     * arrive as a plain collection after being mapped or filtered. Eager load in
     * one query when we can, and fall back to lazy loading when we cannot.
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
}
