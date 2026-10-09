<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\Unit;
use App\Support\Packs;
use App\Support\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads back what a receipt declared one trading unit was made of.
 *
 * A pack declaration belongs to the purchase that carried it, so an item's
 * current composition is the most recent line that declared one. Asking for
 * stock for a whole page at once has to stay one query, not one per item.
 */
class PackReader extends Service
{
    public function __construct(private readonly UnitCatalog $units) {}

    /**
     * The current packs for each of these items, keyed by item id. Items with
     * no declaration yet are simply absent.
     *
     * @param  Collection<int, Item>|array<int, Item>  $items
     * @return array<int, Packs>
     */
    public function forItems(Collection|array $items): array
    {
        $ids = collect($items)->map(fn (Item $item): int => $item->id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // The newest declared line per item, in a single query: the group by
        // finds each item's latest id, the outer query reads its declaration.
        $rows = DB::table('transaction_lines')
            ->select('item_id', 'inner_units')
            ->whereNotNull('inner_units')
            ->whereIn('id', function ($query) use ($ids): void {
                $query->selectRaw('MAX(id)')
                    ->from('transaction_lines')
                    ->whereNotNull('inner_units')
                    ->whereIn('item_id', $ids)
                    ->groupBy('item_id');
            })
            ->get();

        $packs = [];

        foreach ($rows as $row) {
            $packs[(int) $row->item_id] = Packs::fromArray(json_decode((string) $row->inner_units, true));
        }

        return $packs;
    }

    /** The current packs for one item. */
    public function for(Item $item): Packs
    {
        return $this->forItems([$item])[$item->id] ?? Packs::none();
    }

    /**
     * "12 rolls and 24 sachets per carton", for a message.
     *
     * @return string|null null when the item has declared nothing
     */
    public function summary(Packs $packs, Unit $tradingUnit): ?string
    {
        if ($packs->isEmpty()) {
            return null;
        }

        return $packs->summary($this->plurals());
    }

    /**
     * How many of a declared inner unit a base quantity works out to. Half a
     * carton of 12 rolls is 6 rolls; 25kg of a 50kg sack declared as 12 sachets
     * is 6 sachets.
     */
    public function innerAmount(Item $item, float $baseQuantity, Unit|string $unit, Packs $packs): ?float
    {
        $per = $packs->per($unit);
        $trading = $item->displayUnit;

        if ($per === null || $per <= 0 || ! $trading) {
            return null;
        }

        // One trading unit holds $per inner ones, so each inner one is a
        // multiplier/$per slice of base.
        return $baseQuantity * $per / (float) $trading->base_multiplier;
    }

    /**
     * "12 cartons (144 rolls, 288 sachets)" for a stock figure, or the plain
     * label when the item has declared nothing.
     */
    public function breakdown(Item $item, float $baseQuantity, Packs $packs): ?string
    {
        if ($packs->isEmpty() || ! $item->displayUnit) {
            return null;
        }

        $parts = [];

        foreach ($packs->toArray() as $pair) {
            $unit = $this->units->find($pair['unit']);
            $amount = $unit ? $this->innerAmount($item, $baseQuantity, $unit, $packs) : null;

            if ($unit && $amount !== null) {
                $parts[] = $unit->quantityLabel($amount);
            }
        }

        return $parts === [] ? null : '('.implode(', ', $parts).')';
    }

    /**
     * The breakdown for every position on a page, keyed by item id. Items that
     * have declared nothing are absent, so a view can stay silent about them.
     *
     * @param  Collection<int, ItemPosition>  $positions
     * @return array<int, string>
     */
    public function breakdownsFor(Collection $positions): array
    {
        $items = $positions->map(fn (ItemPosition $position): Item => $position->item);
        $declared = $this->forItems($items);
        $breakdowns = [];

        foreach ($positions as $position) {
            $packs = $declared[$position->item->id] ?? null;

            if ($packs) {
                $breakdowns[$position->item->id] = $this->breakdown($position->item, $position->stock(), $packs);
            }
        }

        return $breakdowns;
    }

    /** @return array<string, string> unit code => plural, for reading aloud */
    private function plurals(): array
    {
        return $this->units->all()
            ->mapWithKeys(fn (Unit $unit): array => [
                $unit->code => $unit->plural ?: $unit->label,
            ])
            ->all();
    }
}
