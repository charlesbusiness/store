<?php

namespace App\Services\Record;

use App\Enums\RecordType;
use App\Models\Item;
use App\Models\Unit;
use App\Services\Stock\AvailabilityChecker;
use App\Services\Stock\CostCalculator;
use App\Services\Stock\StockLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Records a sale. Sales may only draw on stock that already exists, and may
 * never bring a new item into existence -- an unknown name is a typo, not a
 * new product.
 */
class SaleRecorder extends RecordWriter
{
    public function __construct(
        LineNormalizer $normalizer,
        AvailabilityChecker $availability,
        private readonly StockLedger $ledger,
        private readonly CostCalculator $costCalculator,
    ) {
        parent::__construct($normalizer, $availability);
    }

    public function type(): RecordType
    {
        return RecordType::Sale;
    }

    /**
     * What the goods sold on this line cost, at the average they were bought for.
     *
     * Read here, inside the caller's transaction and after the item rows are
     * locked, so the figure cannot be taken between two concurrent saves. It uses
     * the same simple average the stock page values inventory at, which is the
     * point: the number on the screen and the number stored are the same number.
     *
     * A line whose item was never bought has no cost to average, and reports null
     * rather than zero.
     *
     * @param  array{item: Item, unit: Unit, qty: float, base_qty: float, unit_price: float, total: float}  $line
     */
    protected function costFor(array $line): ?float
    {
        $item = $line['item'];
        $unit = $line['unit'];
        $qty = (float) ($line['qty'] ?? 0);
        $baseQty = (float) ($line['base_qty'] ?? 0);

        $perUnit = $this->costCalculator->costPerUnit($item, $unit);

        if ($perUnit !== null && $qty > 0) {
            return round($perUnit * $qty, 2);
        }

        return $this->ledger->position($item)->costOf($baseQty);
    }

    /**
     * Sales cannot create items, so every row must already carry an item id.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function resolve(array $rows): ResolvedLines
    {
        $items = new Collection;
        $resolved = [];

        foreach (array_values($rows) as $index => $row) {
            $row = (array) $row;
            $item = Item::with('displayUnit')->find((int) ($row['item_id'] ?? 0));

            if (! $item) {
                throw ValidationException::withMessages([
                    "lines.{$index}.item_id" => 'Choose an item to sell.',
                ]);
            }

            if (! $item->is_active) {
                throw ValidationException::withMessages([
                    "lines.{$index}.item_id" => "{$item->name} is no longer for sale.",
                ]);
            }

            $items->put($item->id, $item);
            $resolved[] = $row;
        }

        return new ResolvedLines($resolved, $items);
    }

    /**
     * Refuse to sell what is not there.
     *
     * The whole form is summed per item first, so selling 2 bags on one line
     * and 1 bag on another is checked as the 3 bags it really is.
     */
    protected function guard(BaseCollection $lines): void
    {
        $shortfalls = $this->availability->shortfalls($lines->all());

        if ($shortfalls === []) {
            return;
        }

        $messages = array_map(
            fn (array $shortfall) => $this->shortfallMessage($shortfall),
            array_values($shortfalls)
        );

        throw ValidationException::withMessages([
            'lines' => 'Not enough stock: '.Str::lower(implode('; ', $messages)).'.',
        ]);
    }

    /**
     * One shortfall, e.g. "Rice is short by 1.5 bags". The missing amount is
     * the difference between what the form asked for and what exists.
     *
     * @param  array{item: Item|null, name: string, wanted: float, available: float}  $shortfall
     */
    private function shortfallMessage(array $shortfall): string
    {
        $item = $shortfall['item'];

        if (! $item?->displayUnit) {
            return $shortfall['name'].' is no longer in stock';
        }

        $available = $shortfall['available'];

        if ($available <= $this->tolerance()) {
            return $shortfall['name'].' is out of stock';
        }

        $missing = $shortfall['wanted'] - $available;

        return sprintf(
            '%s is short by %s',
            $shortfall['name'],
            $item->displayUnit->quantityLabel($this->availability->inDisplayUnits($item, $missing))
        );
    }

    }