<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\TransactionLine;
use App\Services\Stock\StockLedger;
use Tests\TestCase;

/**
 * What a sale cost, and what it made.
 *
 * The whole point of the cost snapshot is that it is frozen: a sale's margin is a
 * fact about the moment it happened, and it must not drift when the item is bought
 * again at a different price. So most of what follows is about that staying still.
 */
class SaleCostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function purchase(array $lines, string $date = '2026-10-01')
    {
        return $this->post(route('purchases.store'), ['transacted_on' => $date, 'lines' => $lines]);
    }

    private function sale(array $lines, string $date = '2026-10-05')
    {
        return $this->post(route('sales.store'), ['transacted_on' => $date, 'lines' => $lines]);
    }

    private function lastLine(): TransactionLine
    {
        return TransactionLine::query()->orderByDesc('id')->firstOrFail();
    }

    public function test_a_sale_freezes_the_cost_it_was_sold_at(): void
    {
        $this->actingAs($this->user());

        // 10 bags bought for 1500 (unit_cost 150), so the average is 150 a bag.
        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);

        $this->sale([['item_id' => 1, 'qty' => 2, 'unit_code' => 'bag', 'unit_price' => 300]]);

        $line = $this->lastLine();

        $this->assertEqualsWithDelta(300.0, $line->cost, 0.001, 'two bags at an average of 150 cost 300');
        $this->assertEqualsWithDelta(300.0, $line->profit(), 0.001, 'sold for 600 against a cost of 300');
    }

    public function test_the_cost_a_sale_records_survives_a_later_purchase_at_another_price(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);

        $this->sale([['item_id' => 1, 'qty' => 2, 'unit_code' => 'bag', 'unit_price' => 300]]);

        $recorded = $this->lastLine();
        $costThen = $recorded->cost;
        $profitThen = $recorded->profit();

        // Buying at 900 a bag (unit_cost) moves the average: (10 x 150 + 10 x 900) over 20 = 525.
        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 1000, 'unit_cost' => 900],
        ]);

        $this->assertEquals(525.0, app(StockLedger::class)->position($recorded->item)->averageCost());
        $this->assertEqualsWithDelta($costThen, $recorded->fresh()->cost, 0.001, 'the cost on the line did not move');
        $this->assertEqualsWithDelta($profitThen, $recorded->fresh()->profit(), 0.001, 'the margin did not move either');
    }

    public function test_a_line_recorded_before_cost_existed_reports_no_cost(): void
    {
        $this->actingAs($this->user());

        // Written straight into the ledger, which is exactly what a line recorded
        // before the cost snapshot existed looks like: the column is simply empty.
        $item = $this->item('Ghost');
        $this->recordLine($item, 'purchase', 5, 'bag', 100, '2026-10-01');
        $line = $this->recordLine($item, 'sale', 2, 'bag', 900, '2026-10-02');

        $this->assertNull($line->cost);
        $this->assertNull($line->profit(), 'a line with no cost has no known margin');

        // The page says so, rather than claiming the goods were sold for nothing.
        $recent = $this->recordFormConfig((string) $this->get(route('sales.create'))->getContent())['recent'][0];

        $this->assertNull($recent['lines'][0]['cost']);
        $this->assertNull($recent['lines'][0]['profit']);
    }

    public function test_a_purchase_records_no_separate_cost_because_its_total_already_is_one(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);

        $line = $this->lastLine();

        $this->assertNull($line->cost, 'a purchase total is the cost; a second figure would only restate it');
        $this->assertNull($line->profit());
    }

    public function test_the_sell_page_shows_cost_and_profit_for_each_sale_line(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);
        $this->sale([['item_id' => 1, 'qty' => 2, 'unit_code' => 'bag', 'unit_price' => 300]]);

        $html = (string) $this->get(route('sales.create'))->getContent();

        $this->assertStringContainsString('Cost', $html, 'the sale table has a cost column');
        $this->assertStringContainsString('Profit', $html, 'the sale table has a profit column');

        $config = $this->recordFormConfig($html);
        $recent = $config['recent'][0];

        $this->assertSame('2026-10-05', $recent['date']);
        $this->assertCount(1, $recent['lines'], 'lines are grouped under the transaction they belong to');
        $this->assertEqualsWithDelta(300.0, $recent['lines'][0]['cost'], 0.001); // 2 bags * 150 cost = 300
        $this->assertEqualsWithDelta(300.0, $recent['lines'][0]['profit'], 0.001); // 600 - 300 = 300

        // The per-sale subtotal, which is the figure a trader is actually after.
        $this->assertEqualsWithDelta(600.0, $recent['total'], 0.001);
        $this->assertEqualsWithDelta(300.0, $recent['cost'], 0.001);
        $this->assertEqualsWithDelta(300.0, $recent['profit'], 0.001);
    }

    public function test_a_sale_holding_an_uncosted_line_reports_no_subtotal_margin(): void
    {
        $this->actingAs($this->user());

        $item = $this->item('Ghost');
        // Two lines on one sale: one with a cost, one without.
        $transaction = $this->recordLine($item, 'sale', 1, 'bag', 300, '2026-10-02');
        $transaction->transaction->lines()->create([
            'item_id' => $item->id,
            'qty' => 2,
            'unit_code' => 'bag',
            'base_qty' => 2,
            'unit_price' => 100,
            'total' => 200,
            'cost' => 50,
        ]);

        $html = (string) $this->get(route('sales.create'))->getContent();
        $recent = $this->recordFormConfig($html)['recent'][0];

        $this->assertEqualsWithDelta(500.0, $recent['total'], 0.001, 'the money in is still known');
        $this->assertNull($recent['cost'], 'one uncosted line makes the whole cost unknown');
        $this->assertNull($recent['profit'], 'so the margin is unknown, not zero');
    }

    public function test_the_buy_page_keeps_its_columns(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);

        $html = (string) $this->get(route('purchases.create'))->getContent();

        // A purchase has no margin to show, and a cost column beside the total
        // would only say the same thing twice.
        $this->assertStringNotContainsString('>Profit<', $html);
        $this->assertStringNotContainsString('>Cost<', $html);
        $this->assertCount(1, $this->recordFormConfig($html)['recent'][0]['lines']);
    }

    public function test_the_recent_limit_counts_sales_rather_than_its_lines(): void
    {
        $this->actingAs($this->user());

        config(['stockly.recent_limit' => 1]);

        $item = $this->item('Ghost');
        $this->recordLine($item, 'sale', 1, 'bag', 300, '2026-10-01');
        $this->recordLine($item, 'sale', 1, 'bag', 400, '2026-10-02');
        $this->recordLine($item, 'sale', 1, 'bag', 500, '2026-10-03');

        $recent = $this->recordFormConfig((string) $this->get(route('sales.create'))->getContent())['recent'];

        // A limit of one sale must not truncate a sale's lines, or its subtotal
        // would quietly add up to a part of itself.
        $this->assertCount(1, $recent);
        $this->assertSame('2026-10-03', $recent[0]['date']);
    }

    public function test_the_form_carries_the_cost_per_unit_so_the_margin_can_be_previewed(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);

        $config = $this->recordFormConfig((string) $this->get(route('sales.create'))->getContent());

        $this->assertEqualsWithDelta(150.0, $config['items'][0]['costPerUnit'], 0.001);

        // Purchases carry no cost, because their total is the cost.
        $purchaseConfig = $this->recordFormConfig((string) $this->get(route('purchases.create'))->getContent());
        $this->assertNull($purchaseConfig['items'][0]['costPerUnit']);
    }

    public function test_a_never_purchased_item_costs_nothing_rather_than_costing_zero(): void
    {
        $this->actingAs($this->user());

        // Nothing has ever been bought, so there is no average to divide by. Null
        // is the only honest answer: zero would value the goods as free and turn
        // any sale of them into pure profit.
        $position = app(StockLedger::class)->position($this->item('Ghost'));

        $this->assertSame(0.0, $position->bought);
        $this->assertNull($position->costOf(10));
        $this->assertSame(0.0, $position->costPerDisplayUnit());
    }

    public function test_the_cost_of_a_line_is_the_average_per_base_unit(): void
    {
        $this->actingAs($this->user());

        // 10 bags for 2000 is 200 a bag. Asked in base units rather than trading
        // units, so the conversion the browser does is covered too.
        $this->purchase([
            ['name' => 'Yam', 'qty' => 10, 'unit_code' => 'bag', 'unit_price' => 200, 'unit_cost' => 150],
        ]);

        $item = Item::query()->firstOrFail();
        $position = app(StockLedger::class)->position($item);

        $this->assertEqualsWithDelta(150.0, $position->averageCost(), 0.001, '1500 paid over 10 received');
        $this->assertEqualsWithDelta(1500.0, $position->costOf(10), 0.001, 'all ten, at 150 each');
        $this->assertEqualsWithDelta(375.0, $position->costOf(2.5), 0.001, 'a part of them scales the same way');
        $this->assertEqualsWithDelta(150.0, $position->costPerDisplayUnit(), 0.001);
    }
}
