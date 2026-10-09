<?php

namespace App\Services\Record;

use App\Enums\RecordType;
use App\Models\Item;
use App\Services\Stock\AvailabilityChecker;
use App\Services\Stock\ItemResolver;
use App\Services\Stock\UnitCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Records a purchase, which is the only side of the ledger allowed to bring a
 * new item into existence.
 */
class PurchaseRecorder extends RecordWriter
{
    public function __construct(
        LineNormalizer $normalizer,
        AvailabilityChecker $availability,
        private readonly ItemResolver $items,
        private readonly UnitCatalog $units,
    ) {
        parent::__construct($normalizer, $availability);
    }

    public function type(): RecordType
    {
        return RecordType::Purchase;
    }

    /**
     * Purchases name their item in free text. A name that already exists
     * resolves to that item; anything else creates one, with its family taken
     * from the unit it was bought in.
     *
     * Every row comes back with item_id filled in, so a line that created an
     * item is written against the item it created.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function resolve(array $rows): ResolvedLines
    {
        $items = new Collection;
        $resolved = [];

        foreach (array_values($rows) as $index => $row) {
            $row = (array) $row;
            $name = Item::normaliseName((string) ($row['name'] ?? ''));

            if ($name === '') {
                throw ValidationException::withMessages([
                    "lines.{$index}.name" => 'Name the item you are buying.',
                ]);
            }

            $item = $this->items->findByName($name);

            if (! $item) {
                $unit = $this->units->find((string) ($row['unit_code'] ?? ''));

                if (! $unit) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_code" => 'Choose the unit you are buying in.',
                    ]);
                }

                $item = $this->items->findOrCreate($name, $unit);
            }

            $items->put($item->id, $item);

            // Set unconditionally: the row arrives with item_id null when the
            // item did not exist yet, and the normaliser looks it up by id.
            $row['item_id'] = $item->id;
            $resolved[] = $row;
        }

        return new ResolvedLines($resolved, $items);
    }
}
