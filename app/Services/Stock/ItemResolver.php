<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\Unit;
use App\Support\Packs;
use App\Support\Service;
use Illuminate\Support\Collection;

/**
 * Turns a typed name into an Item.
 *
 * Purchases may name something that does not exist yet, in which case the item
 * is created and its family inferred from the unit it was bought in. Sales
 * never create: an unknown name there is a mistake, not a new product.
 */
class ItemResolver extends Service
{
    public function __construct(private readonly UnitCatalog $units) {}

    /**
     * Find an item by name, case-insensitively.
     */
    public function findByName(string $name): ?Item
    {
        $key = Item::nameKey($name);

        return $key === '' ? null : Item::with('displayUnit')->where('name_key', $key)->first();
    }

    public function findById(int $id): ?Item
    {
        return Item::with('displayUnit')->find($id);
    }

    /**
     * Find or create an item for a purchase line.
     *
     * An existing item's family is never changed: the unit is checked against
     * the item's own family, so a bag of rice cannot suddenly be sold in
     * litres just because someone typed it that way.
     */
    public function findOrCreate(string $name, Unit $unit): Item
    {
        $name = Item::normaliseName($name);

        if ($existing = $this->findByName($name)) {
            return $existing;
        }

        return Item::create([
            'name' => $name,
            'name_key' => Item::nameKey($name),
            'family' => $unit->family,
            'default_unit_code' => $unit->is_fraction && ! $this->isBaseUnit($unit)
                ? $this->units->baseUnitFor($unit->family)->code
                : $unit->code,
            'is_active' => true,
        ])->load('displayUnit');
    }

    /**
     * Items a sale can pick from: active, holding stock, with their trading
     * unit, the pack they were last received in, and the price they last went
     * for.
     *
     * @return Collection<int, array{item: Item, position: ItemPosition, lastPrice: ?float, packs: Packs}>
     */
    public function sellable(StockLedger $ledger, ActivityReader $activity, PackReader $packs): Collection
    {
        $positions = $ledger->positions(Item::active()->get())
            ->filter(fn (ItemPosition $position) => $this->hasStockLeft($position));

        $items = $positions->map(fn (ItemPosition $p) => $p->item);
        $prices = $activity->lastSalePrices($items);
        $declared = $packs->forItems($items);

        return $positions->map(fn (ItemPosition $position) => [
            'item' => $position->item,
            'position' => $position,
            'lastPrice' => $prices[$position->item->id] ?? null,
            'packs' => $declared[$position->item->id] ?? Packs::none(),
        ])->sortBy(fn (array $row) => mb_strtolower($row['item']->name))->values();
    }

    /** All items, for the purchase form's datalist. */
    public function allForPurchase(): Collection
    {
        return Item::with('displayUnit')->orderBy('name')->get();
    }

    private function hasStockLeft(ItemPosition $position): bool
    {
        return $position->stock() > $this->tolerance();
    }

    private function isBaseUnit(Unit $unit): bool
    {
        return $unit->code === $unit->family->baseUnitCode();
    }
}
