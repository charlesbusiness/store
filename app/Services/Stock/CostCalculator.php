<?php

namespace App\Services\Stock;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Support\Packs;
use Illuminate\Support\Collection;

/**
 * Calculates the cost per unit for any unit (catalogue or declared inner unit)
 * based on purchase history and declared packs.
 *
 * A sale form asks this for every sellable item, times every unit in the item's
 * family. Answering each of those with its own query turned one form load into
 * hundreds of round trips, so the working set is memoised per calculator and
 * the batch entry points fetch purchase history for a whole page in one query.
 */
class CostCalculator
{
    /** @var array<int, Collection<int, TransactionLine>> */
    private array $purchaseLinesCache = [];

    /** @var array<int, Packs> */
    private array $packsCache = [];

    public function __construct(
        private readonly PackReader $packReader,
        private readonly StockLedger $ledger,
        private readonly UnitCatalog $units,
    ) {}

    /**
     * Get the cost per unit for a specific unit of an item.
     */
    public function costPerUnit(Item $item, Unit $unit): ?float
    {
        return $this->resolveCost($item, $unit, $this->getRecentPurchaseLines($item), $this->getPacks($item));
    }

    /**
     * Get cost per unit for all units in an item's family, including declared inner units.
     *
     * @return array<string, float|null> unit code => cost per unit
     */
    public function costPerUnitByUnit(Item $item): array
    {
        return $this->perUnitMapsForItems([
            ['item' => $item, 'position' => $this->ledger->position($item), 'packs' => $this->getPacks($item)],
        ])[$item->id]['cost'];
    }

    /**
     * Get the selling price per unit for a specific unit of an item.
     *
     * Retrieves from purchase history - the selling price declared on buy page.
     */
    public function sellingPricePerUnit(Item $item, Unit $unit): ?float
    {
        return $this->resolveSellingPrice($item, $unit, $this->getRecentPurchaseLines($item), $this->getPacks($item));
    }

    /**
     * Get selling price per unit for all units in an item's family, including declared inner units.
     *
     * @return array<string, float|null> unit code => selling price per unit
     */
    public function sellingPricePerUnitByUnit(Item $item): array
    {
        $packs = $this->getPacks($item);
        $lines = $this->getRecentPurchaseLines($item);

        $prices = [];

        foreach ($this->unitsForItem($item, $packs) as $unit) {
            $price = $this->resolveSellingPrice($item, $unit, $lines, $packs);
            $prices[$unit->code] = $price !== null ? round($price, 2) : null;
        }

        return $prices;
    }

    /**
     * Both per-unit maps for a whole page of items, in a single pass over one
     * purchase-history query.
     *
     * @param  Collection<int, array{item: Item, position?: ?ItemPosition, packs?: ?Packs}>|array<int, array{item: Item, position?: ?ItemPosition, packs?: ?Packs}>  $rows
     * @return array<int, array{cost: array<string, float|null>, sellingPrice: array<string, float|null>}>
     */
    public function perUnitMapsForItems(Collection|array $rows): array
    {
        $rows = collect($rows);
        $ids = $rows->map(fn (array $row): int => $row['item']->id)->all();
        $linesByItem = $this->loadPurchaseLines($ids);

        $maps = [];

        foreach ($rows as $row) {
            /** @var Item $item */
            $item = $row['item'];
            $packs = $row['packs'] ?? $this->getPacks($item);
            $position = $row['position'] ?? null;
            $lines = $linesByItem[$item->id] ?? collect();

            $cost = [];
            $sellingPrice = [];

            foreach ($this->unitsForItem($item, $packs) as $unit) {
                $resolvedCost = $this->resolveCost($item, $unit, $lines, $packs, $position);
                $cost[$unit->code] = $resolvedCost !== null ? round($resolvedCost, 2) : null;

                $resolvedPrice = $this->resolveSellingPrice($item, $unit, $lines, $packs);
                $sellingPrice[$unit->code] = $resolvedPrice !== null ? round($resolvedPrice, 2) : null;
            }

            $maps[$item->id] = ['cost' => $cost, 'sellingPrice' => $sellingPrice];
        }

        return $maps;
    }

    /**
     * Both per-unit maps for a set of items, taking the position for the fallback
     * average cost from the caller when it already has one.
     *
     * @param  Collection<int, Item>|array<int, Item>  $items
     * @param  Collection<int, ItemPosition>|null  $positions  keyed by item id
     * @return array<int, array{cost: array<string, float|null>, sellingPrice: array<string, float|null>}>
     */
    public function perUnitMapsForItemSet(Collection|array $items, ?Collection $positions = null): array
    {
        $rows = collect($items)->map(fn (Item $item): array => [
            'item' => $item,
            'position' => $positions?->get($item->id),
            'packs' => $this->getPacks($item),
        ]);

        return $this->perUnitMapsForItems($rows);
    }

    /**
     * @param  Collection<int, array{item: Item, position?: ?ItemPosition, packs?: ?Packs}>|array<int, array{item: Item, position?: ?ItemPosition, packs?: ?Packs}>  $rows
     * @return array<int, array<string, float|null>>
     */
    public function sellingPricesPerUnitForItems(Collection|array $rows): array
    {
        return collect($this->perUnitMapsForItems($rows))
            ->map(fn (array $maps): array => $sellingPrice = $maps['sellingPrice'])
            ->all();
    }

    /**
     * The units an item can be costed in: its family's catalogue plus any
     * declared inner unit that is not itself in the catalogue.
     *
     * @return Collection<string, Unit> keyed by unit code
     */
    private function unitsForItem(Item $item, Packs $packs): Collection
    {
        $units = $this->units->forFamily($item->family)->keyBy('code');

        foreach ($packs->toArray() as $pack) {
            $code = $pack['unit'];

            if (! $units->has($code) && ($unit = $this->units->find($code))) {
                $units->put($code, $unit);
            }
        }

        return $units;
    }

    /**
     * The cost precedence, shared by the single-unit and batch paths so they can
     * never drift apart.
     */
    private function resolveCost(Item $item, Unit $unit, Collection $lines, Packs $packs, ?ItemPosition $position = null): ?float
    {
        $tradingCode = $item->default_unit_code;

        foreach ($lines as $tl) {
            $tlPacks = $tl->packs();
            $tlTradingCost = $tl->unit_cost;

            // If the unit matches the trading unit, return the trading unit cost
            if ($unit->code === $tradingCode) {
                if ($tlTradingCost !== null) {
                    return (float) $tlTradingCost;
                }

                continue;
            }

            // If the unit is a declared inner unit with an explicit cost
            $innerCost = $tlPacks->costPerUnit($unit);
            if ($innerCost !== null) {
                return (float) $innerCost;
            }

            // If we have a trading cost and can calculate from pieces per inner unit
            if ($tlTradingCost !== null) {
                $perInner = $tlPacks->per($unit);
                if ($perInner !== null && $perInner > 0) {
                    return round((float) $tlTradingCost / $perInner, 4);
                }
            }
        }

        // Fallback: convert average cost to this unit
        $position ??= $this->ledger->position($item);
        if ($position->bought > 0) {
            return $position->averageCost() * $unit->base_multiplier;
        }

        return null;
    }

    /**
     * The selling-price twin of resolveCost(): same precedence, read off the
     * declared selling price rather than the declared cost.
     */
    private function resolveSellingPrice(Item $item, Unit $unit, Collection $lines, Packs $packs): ?float
    {
        $tradingCode = $item->default_unit_code;

        foreach ($lines as $tl) {
            $tlPacks = $tl->packs();
            $tlTradingSellingPrice = $tl->selling_price;

            // If the unit matches the trading unit, return the trading unit selling price
            if ($unit->code === $tradingCode) {
                if ($tlTradingSellingPrice !== null) {
                    return (float) $tlTradingSellingPrice;
                }

                continue;
            }

            // If the unit is a declared inner unit with an explicit selling price
            $innerSellingPrice = $tlPacks->sellingPricePerUnit($unit);
            if ($innerSellingPrice !== null) {
                return (float) $innerSellingPrice;
            }

            // If we have a trading selling price and can calculate from pieces per inner unit
            if ($tlTradingSellingPrice !== null) {
                $perInner = $tlPacks->per($unit);
                if ($perInner !== null && $perInner > 0) {
                    return round((float) $tlTradingSellingPrice / $perInner, 4);
                }
            }
        }

        return null;
    }

    private function getPacks(Item $item): Packs
    {
        return $this->packsCache[$item->id] ??= $this->packReader->for($item);
    }

    private function getRecentPurchaseLines(Item $item): Collection
    {
        return $this->loadPurchaseLines([$item->id])[$item->id];
    }

    /**
     * The 50 most recent purchase lines for each of these items, newest first,
     * fetched for the whole set in one query and memoised per item.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Collection<int, TransactionLine>> keyed by item id
     */
    private function loadPurchaseLines(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $missing = array_values(array_filter($ids, fn (int $id): bool => ! array_key_exists($id, $this->purchaseLinesCache)));

        if ($missing !== []) {
            $rows = TransactionLine::query()
                ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                ->where('transactions.type', RecordType::Purchase->value)
                ->whereIn('transaction_lines.item_id', $missing)
                ->orderByDesc('transactions.transacted_on')
                ->orderByDesc('transaction_lines.id')
                ->get(['transaction_lines.*']);

            $grouped = array_fill_keys($missing, []);

            foreach ($rows as $line) {
                $grouped[(int) $line->item_id][] = $line;
            }

            foreach ($grouped as $id => $list) {
                // Cap per item so a long history does not turn into a long loop.
                $this->purchaseLinesCache[$id] = collect(array_slice($list, 0, 50));
            }
        }

        $result = [];

        foreach (array_unique($ids) as $id) {
            $result[$id] = $this->purchaseLinesCache[$id];
        }

        return $result;
    }

    public function clearCache(): void
    {
        $this->purchaseLinesCache = [];
        $this->packsCache = [];
        $this->packReader->clearCache();
    }
}
