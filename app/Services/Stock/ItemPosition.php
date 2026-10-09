<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\Unit;

/**
 * An item's derived stock position.
 *
 * Nothing here is stored: bought, cost and sold are summed from the ledger on
 * demand, then stock, average cost and stock value are computed from them. That
 * keeps a deleted line from needing a recalculation pass.
 *
 * All quantities are in the item family's base unit unless a name says
 * otherwise, e.g. $displayQuantity.
 */
final readonly class ItemPosition
{
    public function __construct(
        public Item $item,
        public float $bought,
        public float $cost,
        public float $sold,
        public float $revenue,
        public Unit $displayUnit,
    ) {}

    /** Base units bought minus base units sold. May go negative. */
    public function stock(): float
    {
        return $this->bought - $this->sold;
    }

    /**
     * Simple average cost per base unit: total paid divided by total units
     * received. Zero when nothing has been bought, since there is no cost to
     * average.
     */
    public function averageCost(): float
    {
        return $this->bought > 0 ? $this->cost / $this->bought : 0.0;
    }

    /** Cost of one unit in the item's own trading unit, e.g. per bag. */
    public function costPerDisplayUnit(): float
    {
        return $this->averageCost() * $this->displayUnit->base_multiplier;
    }

    /**
     * What a number of base units cost at this average.
     *
     * Null when nothing has ever been bought, because there is no cost to speak
     * of. Null and not zero: zero would value the stock at nothing and make any
     * sale of it look like pure profit.
     */
    public function costOf(float $baseQty): ?float
    {
        return $this->bought > 0 ? $this->averageCost() * $baseQty : null;
    }

    /**
     * What the remaining stock is worth. Negative stock is worth nothing: it
     * means more was sold than ever arrived, which is a bookkeeping gap rather
     * than an asset.
     */
    public function stockValue(): float
    {
        return max($this->stock(), 0) * $this->averageCost();
    }

    public function isOutOfStock(): bool
    {
        return $this->stock() <= 0;
    }

    public function hasTraded(): bool
    {
        return $this->bought > 0 || $this->sold > 0;
    }

    /**
     * The stock figure in the item's own trading unit, e.g. "1.5 bags".
     *
     * A whole trading unit is not required here. Half bags and quarter bags
     * both land in stock, so a bag can be part-used, and 1.5 bags is the
     * figure a trader can actually use. Wholeness is enforced on input, in
     * AvailabilityChecker.
     */
    public function displayStock(): float
    {
        return $this->stock() / $this->displayUnit->base_multiplier;
    }

    public function displaySold(): float
    {
        return $this->sold / $this->displayUnit->base_multiplier;
    }

    public function grossMargin(): float
    {
        return $this->revenue - $this->cost;
    }

    public function stockLabel(): string
    {
        return $this->displayUnit->quantityLabel($this->displayStock());
    }
}
