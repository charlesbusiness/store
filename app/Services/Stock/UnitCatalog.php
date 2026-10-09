<?php

namespace App\Services\Stock;

use App\Enums\UnitFamily;
use App\Models\Item;
use App\Models\Unit;
use App\Support\Service;
use Illuminate\Support\Collection;

/**
 * Read access to the unit vocabulary: look one up, list the ones an item may
 * be measured in, and map between base and display units for presentation.
 */
class UnitCatalog extends Service
{
    /** @var array<string, Unit>|null */
    private ?array $cache = null;

    /**
     * All active units, ordered for a dropdown, cached for the request.
     *
     * @return Collection<int, Unit>
     */
    public function all(): Collection
    {
        if ($this->cache === null) {
            $this->cache = Unit::active()->ordered()->get()->keyBy('code')->all();
        }

        return collect($this->cache);
    }

    public function find(string $code): ?Unit
    {
        return $this->all()->get($code);
    }

    public function findOrFail(string $code): Unit
    {
        return $this->find($code)
            ?? throw new \InvalidArgumentException("Unknown unit [{$code}].");
    }

    /**
     * Units an item may be recorded in: everything sharing its family, so a
     * sack of rice can be sold in halves and quarters but never in litres.
     *
     * @return Collection<int, Unit>
     */
    public function forItem(Item $item): Collection
    {
        return $this->forFamily($item->family);
    }

    /**
     * Every active unit in a family.
     *
     * @return Collection<int, Unit>
     */
    public function forFamily(UnitFamily $family): Collection
    {
        return $this->all()
            ->filter(fn (Unit $unit) => $unit->family === $family)
            ->values();
    }

    /** The base unit of a family, e.g. the gram for anything measured by weight. */
    public function baseUnitFor(UnitFamily $family): Unit
    {
        return $this->findOrFail($family->baseUnitCode());
    }

    /**
     * The active catalogue grouped by family value, for a form that asks for the
     * measurement first and then the unit.
     *
     * Keyed by the enum value rather than the label, because the value is what
     * the measurement <select> posts and what the browser filters on. Each entry
     * carries its own label so the markup can render both without a second pass.
     *
     * @return array<string, array{label: string, units: array<string, string>}>
     */
    public function groupedCatalogue(): array
    {
        return $this->all()
            ->groupBy(fn (Unit $unit) => $unit->family->value)
            ->map(fn (Collection $units, string $family) => [
                'label' => UnitFamily::from($family)->label(),
                'units' => $units->mapWithKeys(fn (Unit $unit) => [$unit->code => $unit->label])->all(),
            ])
            ->all();
    }

    public function isWhole(float $value): bool
    {
        return abs($value - round($value)) < 1e-9;
    }
}
