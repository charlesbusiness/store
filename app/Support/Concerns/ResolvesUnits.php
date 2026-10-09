<?php

namespace App\Support\Concerns;

use App\Enums\UnitFamily;
use App\Models\Item;
use App\Models\Unit;
use App\Support\Packs;
use InvalidArgumentException;

/**
 * Unit arithmetic: the single place where quantities are converted to and from
 * base units. Stock is always stored in base units, so this is the only code
 * that needs to know a kilogram is 1000 grams.
 *
 * Persistence lives in the UnitCatalog service; this trait is pure maths so it
 * can be unit-tested without a database.
 */
trait ResolvesUnits
{
    /**
     * Convert a quantity expressed in $unit into its family's base unit.
     */
    public function toBase(int|float|string $quantity, Unit $unit): float
    {
        return (float) $quantity * $this->multiplierOf($unit);
    }

    /**
     * Convert a base-unit quantity into $unit.
     */
    public function fromBase(int|float|string $baseQuantity, Unit $unit): float
    {
        return (float) $baseQuantity / $this->multiplierOf($unit);
    }

    /**
     * Convert a quantity from one unit to another. Refuses to cross families,
     * because 1 kg cannot be expressed in litres.
     */
    public function convert(int|float|string $quantity, Unit $from, Unit $to): float
    {
        if (! $this->sharesFamily($from, $to)) {
            throw new InvalidArgumentException(
                "Cannot convert {$from->code} ({$from->family->value}) to {$to->code} ({$to->family->value})."
            );
        }

        return $this->fromBase($this->toBase($quantity, $from), $to);
    }

    /**
     * Convert a quantity to base units, allowing for a declared pack.
     *
     * A quantity entered in a declared inner unit divides down to base; anything
     * else uses the catalogue as usual. 6 rolls of a 12-per-carton item is 0.5 of
     * the item, which is the same base figure as entering 0.5 carton directly.
     */
    public function toBaseWithPacks(int|float|string $quantity, Unit $unit, Unit $tradingUnit, Packs $packs): float
    {
        $per = $packs->per($unit);

        if ($per === null || $unit->code === $tradingUnit->code) {
            return $this->toBase($quantity, $unit);
        }

        // Entered in an inner unit: one of those is a fraction of one trading unit.
        return (float) $quantity * (float) $tradingUnit->base_multiplier / $per;
    }

    /**
     * Does this quantity land on a whole number of something inside the unit?
     *
     * A carton declared as 12 rolls per carton splits into halves, thirds and
     * quarters, because each of those is a whole number of rolls. It also splits
     * into sevenths if it was declared as 7. What it cannot do is split into
     * fifths, because 2.4 rolls is not something anybody can hand over.
     */
    public function splitsCleanly(float $quantity, Unit $tradingUnit, Packs $packs): bool
    {
        if ($this->isWhole($quantity)) {
            return true;
        }

        foreach ($packs->toArray() as $pair) {
            $inner = $quantity * $pair['per'];

            if ($this->withinTolerance(round($inner), $inner)) {
                return true;
            }
        }

        return false;
    }

    public function sharesFamily(Unit|UnitFamily $a, Unit|UnitFamily $b): bool
    {
        return $this->familyOf($a) === $this->familyOf($b);
    }

    /** May this unit be used to record quantities for this item? */
    public function canMeasure(Item $item, Unit $unit): bool
    {
        return $this->sharesFamily($item->family, $unit);
    }

    /** Whole-count units reject fractional quantities; measures accept them. */
    public function acceptsFraction(Unit $unit): bool
    {
        return $unit->is_decimal;
    }

    /**
     * Is there stock left, allowing for decimal storage rounding?
     */
    public function hasStock(float $baseQuantity): bool
    {
        return $baseQuantity > $this->tolerance();
    }

    /** Is this number a whole one, within the tolerance? */
    public function isWhole(float $value): bool
    {
        return $this->withinTolerance($value, round($value));
    }

    public function withinTolerance(float $a, float $b): bool
    {
        return abs($a - $b) <= $this->tolerance();
    }

    public function tolerance(): float
    {
        return (float) config('stockly.tolerance', 0.0001);
    }

    protected function familyOf(Unit|UnitFamily $subject): UnitFamily
    {
        return $subject instanceof Unit ? $subject->family : $subject;
    }

    protected function multiplierOf(Unit $unit): float
    {
        $multiplier = (float) $unit->base_multiplier;

        if ($multiplier <= 0) {
            throw new InvalidArgumentException("Unit [{$unit->code}] has a non-positive multiplier.");
        }

        return $multiplier;
    }
}
