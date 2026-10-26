<?php

namespace Tests;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Seed the unit catalogue, which every other test depends on.
     */
    protected function seedUnits(): void
    {
        $this->seed(UnitSeeder::class);
    }

    protected function user(): User
    {
        return User::factory()->create();
    }

    protected function unit(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    /**
     * Create an item.
     */
    protected function item(
        string $name,
        string $family = 'count',
        string $unitCode = 'bag',
        array $attributes = [],
    ): Item {
        return Item::create(array_merge([
            'name' => $name,
            'name_key' => Item::nameKey($name),
            'family' => $family,
            'default_unit_code' => $unitCode,
            'is_active' => true,
        ], $attributes))->load('displayUnit');
    }

    /**
     * Write a single-line transaction straight to the ledger, bypassing the
     * recorders. Use these to arrange a history worth asserting against.
     */
    protected function recordLine(
        Item $item,
        string $type,
        float $qty,
        string $unitCode,
        float $amount,
        ?string $date = null,
        ?float $cost = null,
    ): TransactionLine {
        $unit = $this->unit($unitCode);
        $baseQty = $qty * (float) $unit->base_multiplier;
        $isSale = $type === 'sale';

        $transaction = Transaction::create([
            'type' => $type,
            'transacted_on' => $date ?? Carbon::today()->toDateString(),
        ]);

        return TransactionLine::create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'unit_code' => $unitCode,
            'base_qty' => $baseQty,
            'unit_price' => $isSale ? $amount : round($amount / $qty, 6),
            'total' => round($isSale ? $qty * $amount : $amount, 2),
            // Left null by default, which is what a line recorded before cost was
            // snapshotted looks like.
            'cost' => $cost,
        ]);
    }

}
