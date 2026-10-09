<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Services\Stock\PackReader;
use App\Services\Stock\StockLedger;
use Tests\TestCase;

/**
 * What one trading unit is made of.
 *
 * A receipt may say a carton holds 12 rolls. That is what makes a half carton an
 * honest number, and what lets the next sale be typed in rolls. Stock stays in
 * base units either way, so nothing already recorded has to be reinterpreted.
 */
class PackFlowTest extends TestCase
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

    /** A carton of thyme, received with its contents declared. */
    private function buyThyme(array $packs, float $cartons = 30.0, string $price = '3000'): Item
    {
        $this->actingAs($this->user());

        $this->post(route('purchases.store'), [
            'transacted_on' => '2026-10-05',
            'lines' => [[
                'name' => 'Thyme', 'qty' => $cartons, 'unit_code' => 'carton',
                'unit_price' => $price, 'unit_cost' => '2500', 'inner_units' => $packs,
            ]],
        ])->assertSessionHasNoErrors();

        return Item::where('name', 'Thyme')->firstOrFail();
    }

    private function sell(Item $item, float $qty, string $unit, string $price = '250')
    {
        return $this->post(route('sales.store'), [
            'transacted_on' => '2026-10-05',
            'lines' => [['item_id' => $item->id, 'qty' => $qty, 'unit_code' => $unit, 'unit_price' => $price]],
        ]);
    }

    /**
     * Declaring what a carton holds is what makes a half carton honest, and what
     * lets a later sale be typed in rolls.
     */
    public function test_a_declared_pack_allows_a_fraction_and_a_sale_in_inner_units(): void
    {
        $thyme = $this->buyThyme([['unit' => 'roll', 'per' => 12, 'cost' => 200], ['unit' => 'sachet', 'per' => 24, 'cost' => 100]]);

        $this->assertSame(30.0, $this->ledger()->stockFor($thyme));

        // Half a carton is 6 rolls, and selling them leaves half a carton behind.
        $this->sell($thyme, 6, 'roll')->assertSessionHasNoErrors();

        $this->assertSame(29.5, $this->ledger()->stockFor($thyme));
    }

    public function test_quantities_are_refused_when_they_do_not_come_out_whole(): void
    {
        $thyme = $this->buyThyme([['unit' => 'roll', 'per' => 12, 'cost' => 200], ['unit' => 'sachet', 'per' => 24, 'cost' => 100]]);

        // 0.4 carton is 4.8 rolls, so it is refused rather than quietly rounded,
        // and the way in is spelled out.
        $this->sell($thyme, 0.4, 'carton')
            ->assertSessionHasErrors([
                'lines.0.qty' => '0.4 cartons does not divide into whole 12 rolls or 24 sachets. Enter 9 sachets instead.',
            ]);

        // Half a roll is not a thing.
        $this->sell($thyme, 0.5, 'roll')
            ->assertSessionHasErrors(['lines.0.qty' => 'Enter a whole number of rolls.']);

        $this->assertSame(30.0, $this->ledger()->stockFor($thyme));
    }

    public function test_an_item_cannot_be_its_own_contents_or_borrow_another_family(): void
    {
        $this->actingAs($this->user());

        // A carton cannot hold cartons, nor can it hold kilograms.
        foreach (['carton', 'kg'] as $inner) {
            $this->post(route('purchases.store'), [
                'transacted_on' => '2026-10-05',
                'lines' => [[
                    'name' => 'Thyme', 'qty' => 30, 'unit_code' => 'carton', 'unit_price' => 3000, 'unit_cost' => 2500,
                    'inner_units' => [['unit' => $inner, 'per' => 2, 'cost' => 100]],
                ]],
            ])->assertSessionHasErrors('lines.0.inner_units.0.unit');
        }

        // Half a roll is not a countable number either.
        $this->post(route('purchases.store'), [
            'transacted_on' => '2026-10-05',
            'lines' => [[
                'name' => 'Thyme', 'qty' => 30, 'unit_code' => 'carton', 'unit_price' => 3000, 'unit_cost' => 2500,
                'inner_units' => [['unit' => 'roll', 'per' => 12.5, 'cost' => 100]],
            ]],
        ])->assertSessionHasErrors('lines.0.inner_units.0.per');
    }

    public function test_the_most_recent_receipt_declares_what_the_sell_form_offers(): void
    {
        $thyme = $this->buyThyme([['unit' => 'roll', 'per' => 12, 'cost' => 200]], 10.0);
        $reader = app(PackReader::class);

        $this->assertSame([['unit' => 'roll', 'per' => 12.0, 'cost' => 200.0, 'sellingPrice' => null]], $reader->for($thyme)->toArray());

        // A later receipt supersedes the earlier count.
        $this->buyThyme([['unit' => 'sachet', 'per' => 30, 'cost' => 80]], 10.0);

        $this->assertSame(20.0, $this->ledger()->stockFor($thyme));
        $this->assertSame([['unit' => 'sachet', 'per' => 30.0, 'cost' => 80.0, 'sellingPrice' => null]], $reader->for($thyme)->toArray());

        $this->get(route('sales.create'))->assertOk();

        // 30 sachets to the carton, so 15 of them is half a carton.
        $this->sell($thyme, 15, 'sachet', '100')->assertSessionHasNoErrors();

        $this->assertSame(19.5, $this->ledger()->stockFor($thyme));
    }
}
