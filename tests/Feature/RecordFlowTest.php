<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Services\Record\PurchaseRecorder;
use App\Services\Stock\StockLedger;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Recording a sale or a purchase end to end, through the real controller.
 *
 * These are the rules a browser cannot be trusted to enforce, so they are the
 * ones worth locking down.
 */
class RecordFlowTest extends TestCase
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

    private function purchase(array $lines, string $date = '2026-10-05')
    {
        return $this->post(route('purchases.store'), ['transacted_on' => $date, 'lines' => $lines]);
    }

    private function sale(Item $item, array $lines, string $date = '2026-10-05')
    {
        return $this->post(route('sales.store'), [
            'transacted_on' => $date,
            'lines' => array_map(fn (array $line) => ['item_id' => $item->id] + $line, $lines),
        ]);
    }

    public function test_a_purchase_creates_the_item_with_its_family_from_the_unit(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Yam', 'qty' => 4, 'unit_code' => 'bag', 'unit_price' => 7500, 'unit_cost' => 6000],
            ['name' => 'Gold dust', 'qty' => 2, 'unit_code' => 'kg', 'unit_price' => 45000, 'unit_cost' => 40000],
            // 3 halves is not a whole number of grams, so this trades in grams.
            ['name' => 'Butter', 'qty' => 3, 'unit_code' => 'half_kg', 'unit_price' => 3000, 'unit_cost' => 2500],
        ])->assertSessionHasNoErrors();

        $yam = Item::where('name', 'Yam')->firstOrFail();
        $gold = Item::where('name', 'Gold dust')->firstOrFail();
        $butter = Item::where('name', 'Butter')->firstOrFail();

        $this->assertSame('count', $yam->family->value);
        $this->assertSame('weight', $gold->family->value);
        $this->assertSame('kg', $gold->default_unit_code);
        $this->assertSame('g', $butter->default_unit_code);

        $this->assertSame(4.0, (float) TransactionLine::where('item_id', $yam->id)->value('base_qty'));
        $this->assertSame(1500.0, $this->ledger()->position($butter)->stock());
    }

    public function test_a_purchase_merges_into_an_existing_item_by_name(): void
    {
        $existing = $this->item('Rice', 'count', 'bag');
        $this->recordLine($existing, 'purchase', 5, 'bag', 12500);

        $this->actingAs($this->user());
        $this->purchase([['name' => 'rice', 'qty' => 5, 'unit_code' => 'bag', 'unit_price' => 2800, 'unit_cost' => 2500]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Item::where('name', 'Rice')->count());
        $this->assertSame(10.0, $this->ledger()->position($existing)->stock());
    }

    public function test_a_failed_purchase_writes_nothing_at_all(): void
    {
        $this->actingAs($this->user());

        // Rice already exists and is measured by count, so kilograms are refused
        // rather than quietly remeasuring it.
        $this->item('Rice', 'count', 'bag');

        $this->purchase([['name' => 'Rice', 'qty' => 1, 'unit_code' => 'kg', 'unit_price' => 1000, 'unit_cost' => 900]])
            ->assertSessionHasErrors('lines.0.unit_code');

        // A bag is a whole unit, so 1.5 is refused, and the item named on the good
        // line beside it must not survive as an orphan.
        $this->purchase([
            ['name' => 'Ghost item', 'qty' => 1, 'unit_code' => 'pcs', 'unit_price' => 100, 'unit_cost' => 80],
            ['name' => 'Also ghost', 'qty' => 1.5, 'unit_code' => 'bag', 'unit_price' => 100, 'unit_cost' => 80],
        ])->assertSessionHasErrors('lines.1.qty');

        $this->assertSame(1, Item::count());
        $this->assertSame(0, TransactionLine::count());

        $this->expectException(ValidationException::class);
        app(PurchaseRecorder::class)->record(['transacted_on' => '2026-10-05', 'lines' => []]);
    }

    public function test_a_sale_reduces_stock_by_what_was_sold(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 20, 'bag', 50000);

        $this->actingAs($this->user());
        $this->sale($item, [['qty' => 2, 'unit_code' => 'bag', 'unit_price' => 9000]])
            ->assertSessionHasNoErrors();

        $this->assertSame(18.0, $this->ledger()->position($item)->stock());
        $this->assertSame(18000.0, (float) TransactionLine::where('qty', 2)->value('total'));
    }

    public function test_a_sale_needs_an_active_item_and_cannot_exceed_the_stock(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 3, 'bag', 7500);

        $this->actingAs($this->user());

        // A sale cannot bring an item into existence by naming it.
        $this->post(route('sales.store'), [
            'transacted_on' => '2026-10-05',
            'lines' => [['item_id' => null, 'qty' => 1, 'unit_code' => 'bag', 'unit_price' => 100]],
        ])->assertSessionHasErrors('lines.0.item_id');

        // Nor can it sell one that has been taken off sale.
        $inactive = $this->item('Cement', 'count', 'bag', ['is_active' => false]);
        $this->recordLine($inactive, 'purchase', 3, 'bag', 7500);

        $this->sale($inactive, [['qty' => 1, 'unit_code' => 'bag', 'unit_price' => 100]])
            ->assertSessionHasErrors('lines.0.item_id');

        // 2 + 2 is 4 bags against the 3 in stock, and the form is checked whole.
        $this->sale($item, [
            ['qty' => 2, 'unit_code' => 'bag', 'unit_price' => 9000],
            ['qty' => 2, 'unit_code' => 'bag', 'unit_price' => 9000],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(1, Item::where('is_active', true)->count());
        $this->assertSame(3.0, $this->ledger()->position($item)->stock());
    }

    public function test_fractions_are_converted_to_base_stock(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 1, 'half', 12000);
        $this->recordLine($item, 'purchase', 1, 'bag', 25000);

        $this->actingAs($this->user());

        // 1.5 bags in stock, so a bag and a half is exactly available.
        $this->sale($item, [
            ['qty' => 1, 'unit_code' => 'half', 'unit_price' => 12000],
            ['qty' => 1, 'unit_code' => 'bag', 'unit_price' => 9000],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, $this->ledger()->position($item)->stock());
    }

    public function test_a_multi_line_purchase_is_one_transaction_priced_per_unit(): void
    {
        $this->actingAs($this->user());

        $this->purchase([
            ['name' => 'Rice', 'qty' => 5, 'unit_code' => 'bag', 'unit_cost' => 4500],
            ['name' => 'Sugar', 'qty' => 10, 'unit_code' => 'kg', 'unit_cost' => 1000],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(32500.0, (float) Transaction::firstOrFail()->total());

        // The per unit cost is kept exactly as given, and the total is worked out
        // from it rather than typed in again.
        $lines = TransactionLine::orderBy('id')->get();
        $this->assertSame([4500.0, 1000.0], $lines->map(fn ($line) => (float) $line->unit_price)->all());
        $this->assertSame([22500.0, 10000.0], $lines->map(fn ($line) => (float) $line->total)->all());
    }
}
