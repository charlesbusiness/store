<?php

namespace App\Services\Record;

use App\Enums\RecordType;
use App\Enums\UnitFamily;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Services\Stock\ActivityReader;
use App\Services\Stock\CostCalculator;
use App\Services\Stock\ItemResolver;
use App\Services\Stock\PackReader;
use App\Services\Stock\StockLedger;
use App\Services\Stock\UnitCatalog;
use App\Support\Packs;
use App\Support\Service;
use Illuminate\Support\Collection;

/**
 * Builds everything the sell and buy forms need in one array.
 *
 * The unit catalogue goes to the browser as plain JSON so the page can narrow the
 * unit dropdown to the chosen measurement and preview a line total without a
 * round trip. The server never trusts any of it: every figure is recomputed when
 * the form is posted, and the family of a new item is read off the unit bought
 * in rather than from the browser.
 */
class RecordFormData extends Service
{
    public function __construct(
        private readonly ItemResolver $items,
        private readonly StockLedger $ledger,
        private readonly UnitCatalog $units,
        private readonly ActivityReader $activity,
        private readonly PackReader $packs,
        private readonly CostCalculator $costCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(RecordType $type, int $page = 1): array
    {
        $limit = (int) config('stockly.recent_limit', 8);
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $total = $this->activity->transactionsOf($type);
        $totalPages = (int) ceil($total / $limit);

        return [
            'type' => $type->value,
            'isSale' => $type === RecordType::Sale,
            'today' => today()->toDateString(),
            'items' => $type === RecordType::Sale ? $this->sellableItems() : $this->purchaseItems(),
            // A list, not a keyed map: the browser iterates it to build the
            // measurement picker, and an object has nothing to iterate.
            'families' => UnitFamily::choices(),
            'units' => $this->unitCatalogue(),
            'currency' => config('stockly.currency'),
            'moneyDecimals' => (int) config('stockly.money_decimals', 2),
            'recent' => $this->recentLines($type, $limit, $offset),
            'recentTotal' => $this->activity->transactionsOf($type),
            'recentLimit' => $limit,
            'recentPage' => $page,
            'recentTotalPages' => $totalPages,
        ];
    }

    /**
     * Only items that actually have stock, with what is left and what they last
     * went for.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sellableItems(): array
    {
        return $this->items->sellable($this->ledger, $this->activity, $this->packs)
            ->map(fn (array $row) => [
                'id' => $row['item']->id,
                'name' => $row['item']->name,
                'family' => $row['item']->family->value,
                'unit' => $row['position']->displayUnit->code,
                'stock' => $row['position']->displayStock(),
                'stockLabel' => $row['position']->stockLabel(),
                'lastPrice' => $row['lastPrice'] === null
                    ? null
                    : round($row['lastPrice'], 2),
                // What this item's stock costs per trading unit, so the form can
                // show the margin on a line before it is saved. It is the same
                // average the server freezes onto the line, so the figure the
                // trader sees is the figure that gets stored -- barring a purchase
                // landing in between. Null when nothing has ever been bought,
                // since there is no cost to average yet.
                'costPerUnit' => $row['position']->bought > 0
                    ? round($row['position']->costPerDisplayUnit(), 2)
                    : null,
                // Cost per unit for each unit in the item's family, for the sell page
                // to show as readonly when a unit is selected.
                'costPerUnitByUnit' => $this->costPerUnitByUnit($row['item']),
                // Selling price per unit for each unit in the item's family (from purchase history),
                // to show as reference below the selling price input on the sell page.
                'sellingPricePerUnitByUnit' => $this->costCalculator->sellingPricePerUnitByUnit($row['item']),
                'packs' => $row['packs']->toArray(),
            ])
            ->all();
    }

    /**
     * Get cost per unit for each unit in the item's family from purchase history,
     * including declared inner units.
     *
     * @param  \App\Models\Item  $item
     * @return array<string, float|null> unit code => cost per unit
     */
    private function costPerUnitByUnit(\App\Models\Item $item): array
    {
        return $this->costCalculator->costPerUnitByUnit($item);
    }

    /**
     * Every item, for the datalist that suggests names while typing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function purchaseItems(): array
    {
        $items = $this->items->allForPurchase();
        $declared = $this->packs->forItems($items);
        $lastSellingPrices = $this->activity->lastSalePrices($items);

        return $items
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'family' => $item->family->value,
                'unit' => $item->default_unit_code,
                'stock' => null,
                'stockLabel' => null,
                'lastPrice' => null,
                // Last selling price for pre-filling on buy page
                'lastSellingPrice' => $lastSellingPrices[$item->id] ?? null,
                // Purchases have no margin to preview, so no cost travels down.
                'costPerUnit' => null,
                // Prefilled so a repeat receipt of the same goods does not have
                // to be retyped. Optional on submit, so leaving it blank is fine.
                'packs' => ($declared[$item->id] ?? \App\Support\Packs::none())->toArray(),
            ])
            ->all();
    }

    /**
     * The whole active unit catalogue, keyed by family value, so the browser can
     * show one measurement's units at a time and can offer every measurement for
     * a brand new item.
     *
     * Multipliers travel with it because the browser needs them to total a
     * stock check across lines recorded in different units.
     *
     * @return array<string, array<int, array{code: string, label: string, decimal: bool, fraction: bool, multiplier: float}>>
     */
    private function unitCatalogue(): array
    {
        $catalogue = [];

        foreach ($this->units->all() as $unit) {
            $catalogue[$unit->family->value][] = [
                'code' => $unit->code,
                'label' => $unit->label,
                // Sent down so the browser can say "0.4 cartons" the same way
                // the server does, rather than inventing its own plural.
                'plural' => $unit->plural ?: $unit->label,
                'decimal' => $unit->is_decimal,
                'fraction' => $unit->is_fraction,
                'multiplier' => (float) $unit->base_multiplier,
            ];
        }

        return $catalogue;
    }

    /**
     * The most recent transactions of a type, each with its lines and a subtotal.
     *
     * Grouped by transaction so the page can answer "what did that sale make me"
     * as well as "what was on it". Cost and profit come off each line's own
     * snapshot, never off a live average, so a sale recorded last month still
     * shows what it made last month.
     *
     * @param  int  $limit
     * @param  int  $offset
     * @return array<int, array<string, mixed>>
     */
    private function recentLines(RecordType $type, int $limit, int $offset): array
    {
        return $this->activity->recentGrouped($type, $limit, $offset)
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->transacted_on->toDateString(),
                // Formatted here so the view stays a table and not a date library.
                'dateLabel' => $transaction->transacted_on->toFormattedDateString(),
                'paid' => $transaction->paid,
                'lines' => $transaction->lines->map(fn (TransactionLine $line) => [
                    'id' => $line->id,
                    'name' => $line->item?->name ?? '(deleted item)',
                    'quantity' => $line->unit?->quantityLabel($line->qty) ?? (string) $line->qty,
                    'qty' => (float) $line->qty,
                    'total' => round((float) $line->total, 2),
                    'cost' => $line->cost === null ? null : round((float) $line->cost, 2),
                    'profit' => $line->profit() === null ? null : round($line->profit(), 2),
                ])->all(),
                // Only meaningful once every line has a cost. A single unknown line
                // makes the whole subtotal unknown, and a partial sum that looked
                // complete would be worse than no figure at all.
                'total' => round((float) $transaction->lines->sum('total'), 2),
                'cost' => $this->summedCost($transaction->lines),
                'profit' => $this->summedProfit($transaction->lines),
            ])
            ->all();
    }

    /**
     * The cost of a whole transaction, or null when any line's cost is unknown.
     *
     * @param  Collection<int, TransactionLine>  $lines
     */
    private function summedCost(Collection $lines): ?float
    {
        return $lines->contains(fn (TransactionLine $line) => $line->cost === null)
            ? null
            : round((float) $lines->sum('cost'), 2);
    }

    /**
     * @param  Collection<int, TransactionLine>  $lines
     */
    private function summedProfit(Collection $lines): ?float
    {
        return $lines->contains(fn (TransactionLine $line) => $line->profit() === null)
            ? null
            : round((float) $lines->sum(fn (TransactionLine $line) => $line->profit()), 2);
    }
}
