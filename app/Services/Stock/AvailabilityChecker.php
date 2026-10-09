<?php

namespace App\Services\Stock;

use App\Enums\UnitFamily;
use App\Models\Item;
use App\Models\Unit;
use App\Support\Packs;
use App\Support\Service;
use Illuminate\Support\Collection;

/**
 * Decides which units a quantity may be recorded in, and how much of an item
 * is genuinely available to sell.
 *
 * The rules live here rather than in a controller so the form, the recorder and
 * the tests all agree on them.
 */
class AvailabilityChecker extends Service
{
    public function __construct(
        private readonly UnitCatalog $units,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * Units valid for an item, i.e. those in its own family.
     *
     * @return Collection<int, Unit>
     */
    public function unitsFor(Item $item): Collection
    {
        return $this->units->forItem($item);
    }

    public function canMeasure(Item $item, Unit $unit): bool
    {
        return $this->canMeasureFamily($item->family, $unit);
    }

    public function canMeasureFamily(UnitFamily $family, Unit $unit): bool
    {
        return $unit->family === $family;
    }

    /**
     * Is a quantity a sensible one to record?
     *
     * Whole-count units refuse fractions: buy "2 half" or "2 pcs", not
     * "2.5 pcs". A fraction of a piece has no meaning on a count family, and
     * letting it through would make stock figures unreadable.
     *
     * A declared pack relaxes this for the item's trading unit, and only where
     * the fraction lands on a whole number of something inside the carton. Half
     * a carton of 12 rolls is 6 rolls, so half a carton is honest; two fifths of
     * it is 4.8 rolls, so it is not. A quantity entered in an inner unit still
     * has to be whole, because half a sachet is not a thing.
     */
    public function acceptsQuantity(Unit $unit, float $quantity, Unit $tradingUnit, Packs $packs): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        if ($unit->is_decimal) {
            return true;
        }

        if ($unit->code !== $tradingUnit->code) {
            return $this->units->isWhole($quantity);
        }

        return $this->units->splitsCleanly($quantity, $tradingUnit, $packs);
    }

    /**
     * The closest quantity that would have been accepted, expressed in an inner
     * unit so it can be offered back to the trader. 0.4 of a carton of 24
     * sachets becomes 9 sachets.
     *
     * @return array{qty: float, count: float, unit: Unit}|null
     */
    public function nearestInnerSplit(Unit $tradingUnit, float $quantity, Packs $packs): ?array
    {
        $best = null;

        foreach ($packs->toArray() as $pair) {
            $unit = $this->units->find($pair['unit']);

            if (! $unit || $unit->code === $tradingUnit->code) {
                continue;
            }

            $count = floor($quantity * $pair['per'] + $this->tolerance());

            if ($count < 1) {
                continue;
            }

            $qty = $count / $pair['per'];

            if ($best === null || $qty > $best['qty']) {
                $best = ['qty' => $qty, 'count' => $count, 'unit' => $unit];
            }
        }

        return $best;
    }

    /** Base units available for one item. */
    public function available(Item $item): float
    {
        return $this->ledger->stockFor($item);
    }

    /**
     * Which of the pending lines cannot be satisfied.
     *
     * Quantities are summed per item across the whole form, so selling 2 bags
     * on one line and 1 bag on another is checked as the 3 bags it really is.
     * Used for the live check in the browser and again under a row lock when
     * saving.
     *
     * @param  array<int, array{item: Item, base_qty: float}>  $lines
     * @return array<int, array{item: Item, name: string, wanted: float, available: float}> keyed by item id
     */
    public function shortfalls(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $requested = [];

        foreach ($lines as $line) {
            $id = $line['item']->id;
            $requested[$id] = ($requested[$id] ?? 0) + (float) $line['base_qty'];
        }

        $positions = $this->ledger->positions(
            Item::with('displayUnit')->whereIn('id', array_keys($requested))->get()
        );

        $shortfalls = [];

        foreach ($requested as $itemId => $wanted) {
            $position = $positions->get($itemId);
            $available = $position?->stock() ?? 0;

            if ($wanted > $available + $this->tolerance()) {
                $shortfalls[$itemId] = [
                    'item' => $position?->item,
                    'name' => $position?->item->name ?? 'That item',
                    'wanted' => $wanted,
                    'available' => $available,
                ];
            }
        }

        return $shortfalls;
    }

    /** Restate a base quantity in an item's own trading unit, for messages. */
    public function inDisplayUnits(Item $item, float $baseQuantity): float
    {
        $unit = $item->displayUnit;

        return $unit ? $baseQuantity / $unit->base_multiplier : $baseQuantity;
    }
}
