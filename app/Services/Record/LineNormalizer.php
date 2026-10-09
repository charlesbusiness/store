<?php

namespace App\Services\Record;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\Unit;
use App\Services\Stock\AvailabilityChecker;
use App\Services\Stock\PackReader;
use App\Services\Stock\UnitCatalog;
use App\Support\Packs;
use App\Support\Service;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns raw form input into a validated list of storable lines.
 *
 * Both tabs state a price for one unit of the line and let the total fall out of
 * the multiplication. Each line is then converted into base units, which is the
 * single number everything downstream aggregates, honouring any pack the
 * receipt declared.
 */
class LineNormalizer extends Service
{
    public function __construct(
        private readonly UnitCatalog $units,
        private readonly AvailabilityChecker $availability,
        private readonly PackReader $packs,
    ) {}

    /**
     * Normalise submitted lines.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, Item>  $items  keyed by item id
     * @return Collection<int, array{item: Item, unit: Unit, qty: float, base_qty: float, unit_price: float, total: float, inner_units: array}>
     *
     * @throws ValidationException
     */
    public function normalise(RecordType $type, array $rows, Collection $items): Collection
    {
        $current = $this->packs->forItems($items);

        return collect(array_values($rows))
            ->map(fn (array $row, int $index) => $this->normaliseLine($type, $index, $row, $items, $current));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, Item>  $items
     * @param  array<int, Packs>  $current  the item's declared packs, keyed by item id
     * @return array{item: Item, unit: Unit, qty: float, base_qty: float, unit_price: float, total: float, inner_units: array}
     */
    private function normaliseLine(RecordType $type, int $index, array $row, Collection $items, array $current): array
    {
        $bag = "lines.{$index}";
        $item = $items->get((int) ($row['item_id'] ?? 0));

        if (! $item) {
            throw ValidationException::withMessages([$bag.'.item_id' => 'Choose an item for this line.']);
        }

        $unit = $this->units->find((string) ($row['unit_code'] ?? ''));

        if (! $unit) {
            throw ValidationException::withMessages([$bag.'.unit_code' => 'Choose a unit for this line.']);
        }

        if (! $this->availability->canMeasure($item, $unit)) {
            throw ValidationException::withMessages([
                $bag.'.unit_code' => sprintf(
                    '%s is measured by %s, so it cannot be recorded in %s.',
                    $item->name,
                    strtolower($item->family->label()),
                    strtolower($unit->label)
                ),
            ]);
        }

        // A purchase declares its own packs; a sale uses what the item currently
        // has, since the receipt that declared them has already been written.
        $innerDecl = $row['inner_units'] ?? null;
        $packs = $type === RecordType::Purchase
            ? Packs::fromArray($innerDecl)
            : ($current[$item->id] ?? Packs::none());

        $this->guardDeclaredPacks($item, $packs, $bag);

        $tradingUnit = $this->units->findOrFail($item->default_unit_code);
        $qty = (float) ($row['qty'] ?? 0);

        if (! $this->availability->acceptsQuantity($unit, $qty, $tradingUnit, $packs)) {
            throw ValidationException::withMessages([
                $bag.'.qty' => $this->quantityMessage($unit, $tradingUnit, $qty, $packs),
            ]);
        }

        $baseQty = $this->toBaseWithPacks($qty, $unit, $tradingUnit, $packs);

        if ($type === RecordType::Purchase) {
            // For purchases: unit_cost is required and becomes the effective unit_price
            $rawUnitCost = $row['unit_cost'] ?? null;
            if ($rawUnitCost === null || $rawUnitCost === '') {
                throw ValidationException::withMessages([
                    $bag.'.unit_cost' => sprintf('Cost per %s is required.', $unit->label),
                ]);
            }
            $unitCost = round((float) $rawUnitCost, 2);
            $unitPrice = $unitCost; // For purchases, price = cost

            // Optional selling price for purchases
            $rawSellingPrice = $row['selling_price'] ?? null;
            $sellingPrice = ($rawSellingPrice !== null && $rawSellingPrice !== '')
                ? round((float) $rawSellingPrice, 2)
                : null;
        } else {
            // For sales: unit_price (selling price) is required
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            if ($unitPrice <= 0) {
                throw ValidationException::withMessages([
                    $bag.'.unit_price' => 'Enter the selling price for one unit of this line.',
                ]);
            }
            $unitCost = $row['unit_cost'] !== null && $row['unit_cost'] !== ''
                ? round((float) $row['unit_cost'], 2)
                : null;
        }

        return [
            'item' => $item,
            'unit' => $unit,
            'qty' => $qty,
            'base_qty' => $baseQty,
            'unit_price' => $unitPrice,
            'total' => round($qty * $unitPrice, 2),
            'inner_units' => $packs->toArray(),
            'unit_cost' => $unitCost,
            'selling_price' => $type === RecordType::Sale ? $unitPrice : ($sellingPrice ?? null),
        ];
    }

    /**
     * A declared pack has to be countable, and cannot be a fraction itself: 12
     * rolls per carton is fine, half a roll is not.
     *
     * @throws ValidationException
     */
    private function guardDeclaredPacks(Item $item, Packs $packs, string $bag): void
    {
        foreach ($packs->toArray() as $slot => $pair) {
            $unit = $this->units->find($pair['unit']);

            if (! $unit) {
                throw ValidationException::withMessages([
                    $bag.".inner_units.{$slot}.unit" => 'Choose a unit from the catalogue.',
                ]);
            }

            if ($unit->code === $item->default_unit_code) {
                throw ValidationException::withMessages([
                    $bag.".inner_units.{$slot}.unit" => sprintf(
                        '%s is the unit %s is traded in, so it cannot also be the contents of one.',
                        $unit->label,
                        $item->name
                    ),
                ]);
            }

            if (! $this->availability->canMeasure($item, $unit)) {
                throw ValidationException::withMessages([
                    $bag.".inner_units.{$slot}.unit" => sprintf(
                        '%s is measured by %s, so %s cannot be counted inside it.',
                        $item->name,
                        strtolower($item->family->label()),
                        strtolower($unit->label)
                    ),
                ]);
            }

            if (! $this->units->isWhole($pair['per'])) {
                throw ValidationException::withMessages([
                    $bag.".inner_units.{$slot}.per" => 'Enter a whole number of these per unit.',
                ]);
            }

            // Cost per inner unit is required when an inner unit is declared
            if ($pair['cost'] === null || $pair['cost'] === '') {
                throw ValidationException::withMessages([
                    $bag.".inner_units.{$slot}.cost" => sprintf(
                        'Cost per %s is required.',
                        $unit->label
                    ),
                ]);
            }
        }
    }

    /**
     * Explain a rejected quantity in the trader's own terms, and offer the
     * nearest quantity that would have worked.
     */
    private function quantityMessage(Unit $unit, Unit $tradingUnit, float $qty, Packs $packs): string
    {
        if ($qty <= 0) {
            return 'Enter a quantity greater than zero.';
        }

        // Entered in an inner unit: those are counted whole, whatever the pack.
        if ($unit->code !== $tradingUnit->code) {
            return sprintf('Enter a whole number of %s.', $unit->plural ?: strtolower($unit->label));
        }

        if ($packs->isEmpty()) {
            $fraction = $this->units->forFamily($unit->family)
                ->first(fn (Unit $candidate): bool => $candidate->is_fraction);

            return sprintf(
                '%s is a whole unit, so the quantity must be a whole number.%s',
                ucfirst($unit->label),
                $fraction ? ' To record part of one, use '.strtolower($fraction->label).' instead.' : ''
            );
        }

        $split = $this->availability->nearestInnerSplit($tradingUnit, $qty, $packs);

        // Name what the unit is made of, so the way in is obvious.
        $contents = collect($packs->toArray())
            ->mapWithKeys(fn (array $pack): array => [
                $pack['unit'] => $this->units->findOrFail($pack['unit'])->plural ?: $pack['unit'],
            ]);

        return sprintf(
            '%s does not divide into whole %s.%s',
            $unit->quantityLabel($qty),
            $packs->summary($contents->all()),
            $split ? ' Enter '.$split['unit']->quantityLabel($split['count']).' instead.' : ''
        );
    }

    /** Sum of every line total, for the cart preview and the stored header. */
    public function totalOf(Collection $lines): float
    {
        return round($lines->sum('total'), 2);
    }
}
