<?php

namespace Database\Seeders;

use App\Enums\RecordType;
use App\Enums\UnitFamily;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A small sample ledger so the reports, trend chart and stock table have
 * something to show on a fresh install. Only loaded when STOCKLY_DEMO is on.
 */
class DemoLedgerSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('stockly.demo')) {
            return;
        }

        $this->command?->info('Loading the demo ledger.');

        $catalogue = [
            ['Rice', UnitFamily::Count, 'bag', '50kg sack', [['bag', 4, 24000], ['bag', 2, 26000]]],
            ['Beans', UnitFamily::Count, 'bag', '30kg sack', [['bag', 3, 31000]]],
            ['Vegetable oil', UnitFamily::Count, 'carton', '12 x 25l', [['carton', 5, 48000]]],
            ['Tomatoes', UnitFamily::Weight, 'kg', null, [['kg', 25, 6400], ['kg', 10, 5800]]],
            ['Onions', UnitFamily::Weight, 'kg', 'sold by weight', [['bag_25kg', 4, 52000]]],
            ['Bottles', UnitFamily::Count, 'dozen', 'crate of 12', [['dozen', 6, 5400]]],
            ['Sugar', UnitFamily::Weight, 'kg', null, [['sack_50kg', 2, 118000]]],
            ['Milo', UnitFamily::Count, 'bag', '25kg bag', [['bag', 8, 52000]]],
            ['Indomie', UnitFamily::Count, 'carton', '40 packs', [['carton', 10, 23500]]],
            ['Cement', UnitFamily::Weight, 'sack_50kg', null, [['sack_50kg', 3, 13200]]],
        ];

        foreach ($catalogue as [$name, $family, $unitCode, $packSize, $purchases]) {
            $item = $this->item($name, $family, $unitCode, $packSize);

            foreach ($purchases as [$purchaseUnit, $qty, $cost]) {
                $this->purchase($item, $purchaseUnit, $qty, $cost);
            }
        }

        $this->sales();
    }

    private function item(string $name, UnitFamily $family, string $unitCode, ?string $packSize): Item
    {
        return Item::create([
            'name' => $name,
            'name_key' => Item::nameKey($name),
            'family' => $family,
            'default_unit_code' => $unitCode,
            'pack_size' => $packSize,
            'is_active' => true,
        ])->load('displayUnit');
    }

    /** Record a purchase in an existing unit of the item's own family. */
    private function purchase(Item $item, string $unitCode, float $qty, float $cost): void
    {
        // Not created on the fly: a typo in the sample data should fail loudly
        // rather than invent a unit.
        $unit = Unit::where('code', $unitCode)->firstOrFail();

        $this->record(RecordType::Purchase, $item, $unit, $qty, null, $cost, $this->daysAgo(30));
    }

    /**
     * A spread of sales over the last few weeks, so every reporting grain has
     * something in it.
     */
    private function sales(): void
    {
        $plan = [
            ['Rice', 'bag', 1, 32000, 3],
            ['Rice', 'half', 3, 16000, 6],
            ['Beans', 'bag', 1, 38000, 9],
            ['Tomatoes', 'kg', 6, 7500, 1],
            ['Tomatoes', 'kg', 9, 7000, 4],
            ['Onions', 'half_kg', 4, 2900, 2],
            ['Vegetable oil', 'carton', 1, 56000, 5],
            ['Bottles', 'dozen', 1, 6600, 8],
            ['Indomie', 'carton', 2, 27000, 11],
            ['Milo', 'bag', 1, 92000, 14],
            ['Sugar', 'sack_50kg', 1, 134000, 18],
            ['Cement', 'sack_50kg', 4, 15000, 21],
            ['Rice', 'bag', 2, 33000, 25],
            ['Beans', 'quarter', 4, 9500, 28],
        ];

        foreach ($plan as [$name, $unitCode, $qty, $unitPrice, $daysAgo]) {
            $item = Item::where('name_key', Item::nameKey($name))->with('displayUnit')->first();
            $unit = Unit::where('code', $unitCode)->first();

            if ($item && $unit) {
                $this->record(RecordType::Sale, $item, $unit, $qty, $unitPrice, null, $this->daysAgo($daysAgo));
            }
        }
    }

    /**
     * Write one transaction with a single line, computing the denormalised
     * figures the way LineNormalizer does.
     */
    private function record(
        RecordType $type,
        Item $item,
        Unit $unit,
        float $qty,
        ?float $unitPrice,
        ?float $total,
        string $date
    ): void {
        $baseQty = $qty * (float) $unit->base_multiplier;
        $total ??= $qty * $unitPrice;

        $transaction = DB::transaction(function () use ($type, $date, $item, $unit, $qty, $unitPrice, $total, $baseQty) {
            $created = Transaction::create([
                'type' => $type,
                'transacted_on' => $date,
            ]);

            TransactionLine::create([
                'transaction_id' => $created->id,
                'item_id' => $item->id,
                'qty' => $qty,
                'unit_code' => $unit->code,
                'base_qty' => $baseQty,
                'unit_price' => $unitPrice ?? round($total / $qty, 6),
                'total' => round($total, 2),
            ]);

            return $created;
        });
    }

    private function daysAgo(int $days): string
    {
        return CarbonImmutable::today()->subDays($days)->toDateString();
    }
}
