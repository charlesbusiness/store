<?php

namespace App\Services\Record;

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

/**
 * The outcome of turning submitted rows into real items.
 *
 * Rows come back with item_id filled in, including for a purchase line that
 * named an item nobody had bought before and so caused one to be created. The
 * normaliser only has to look items up by id, whatever the record type is.
 */
final class ResolvedLines
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, Item>  $items  keyed by item id
     */
    public function __construct(
        public readonly array $rows,
        public readonly Collection $items,
    ) {}
}
