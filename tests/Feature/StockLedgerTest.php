<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Services\Stock\StockLedger;
use Tests\TestCase;

/**
 * The derived stock arithmetic. Stock, cost and value are never stored in a
 * column, so these assert the maths that the rest of the app reads.
 */
class StockLedgerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    public function test_a_position_is_bought_minus_sold_in_base_units(): void
    {
        // 2 dozen = 24 pieces for 6,000, then 5 pieces sold at 2,500 each leaves 19.
        $item = $this->item('Bottles', 'count', 'dozen');

        $this->recordLine($item, 'purchase', 2, 'dozen', 6000);
        $this->recordLine($item, 'sale', 5, 'pcs', 2500);

        $position = $this->ledger()->position($item);

        $this->assertSame(19.0, $position->stock());
        $this->assertSame(6000.0, $position->cost);
        // 5 pieces at 2,500.
        $this->assertSame(12500.0, $position->revenue);
    }

    public function test_cost_is_a_simple_average_over_base_units(): void
    {
        // 2 bags for 20,000 plus 1 dozen for 36,000 is 14 pieces for 56,000.
        $item = $this->item('Beans', 'count', 'bag');

        $this->recordLine($item, 'purchase', 2, 'bag', 20000);
        $this->recordLine($item, 'purchase', 1, 'dozen', 36000);

        $position = $this->ledger()->position($item);

        $this->assertSame(14.0, $position->stock());
        $this->assertEqualsWithDelta(4000.0, $position->averageCost(), 0.001);
        $this->assertEqualsWithDelta(4000.0, $position->costPerDisplayUnit(), 0.001);
    }

    public function test_a_negative_position_is_out_of_stock_and_worth_nothing(): void
    {
        $item = $this->item('Cement', 'weight', 'sack_50kg');

        $this->recordLine($item, 'purchase', 1, 'sack_50kg', 13200);
        $this->recordLine($item, 'sale', 2, 'sack_50kg', 12000);

        $position = $this->ledger()->position($item);

        $this->assertSame(-50000.0, $position->stock());
        $this->assertTrue($position->isOutOfStock());
        $this->assertSame(0.0, $position->stockValue());
    }

    public function test_deleting_a_line_adjusts_stock_without_reconciliation(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $line = $this->recordLine($item, 'purchase', 10, 'bag', 25000);

        $this->actingAs($this->user());

        $this->assertSame(10.0, $this->ledger()->position($item)->stock());

        $this->delete(route('records.lines.destroy', $line))->assertRedirect();

        $this->assertSame(0.0, $this->ledger()->position($item)->stock());

        // A transaction with nothing left in it goes too.
        $this->assertSame(0, $item->transactions()->count());
    }

    public function test_the_total_values_each_position_in_its_own_family(): void
    {
        $rice = $this->item('Rice', 'count', 'bag');
        $sugar = $this->item('Sugar', 'weight', 'kg');

        $this->recordLine($rice, 'purchase', 10, 'bag', 25000);
        $this->recordLine($rice, 'sale', 3, 'bag', 9000);
        $this->recordLine($sugar, 'purchase', 50, 'kg', 60000);
        $this->recordLine($sugar, 'sale', 10, 'kg', 15000);

        // 7 bags at 2,500 = 17,500, plus the remaining 40 kg of sugar carried at
        // the 1.20 per gram it was bought for = 48,000.
        $this->assertSame(65500.0, $this->ledger()->totalStockValue());
    }

    public function test_positions_stay_keyed_by_item_id(): void
    {
        $a = $this->item('Apples');
        $b = $this->item('Bananas');
        $this->recordLine($a, 'purchase', 1, 'pcs', 100);
        $this->recordLine($b, 'purchase', 1, 'pcs', 100);

        $positions = $this->ledger()->positions(Item::all());

        $this->assertSame([$a->id, $b->id], $positions->keys()->all());
        $this->assertSame('Apples', $positions->get($a->id)->item->name);
    }
}
