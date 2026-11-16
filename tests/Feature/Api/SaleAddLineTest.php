<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Tenant;
use Tests\TestCase;

class SaleAddLineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function stockedItem(string $name = 'Yam', float $qty = 10): Item
    {
        $item = $this->item($name);
        $this->recordLine($item, 'purchase', $qty, 'bag', 3000, '2026-10-20');

        return $item;
    }

    private function sale(): Sale
    {
        $item = $this->stockedItem();
        $line = $this->recordLine($item, 'sale', 2, 'bag', 5000, '2026-10-24');

        return $line->transaction()->firstOrFail();
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>}
     */
    private function linePayload(Item $item, array $overrides = []): array
    {
        return [
            'lines' => [
                array_merge([
                    'item_id' => $item->id,
                    'qty' => 1,
                    'unit_code' => 'bag',
                    'unit_price' => 4000,
                ], $overrides),
            ],
        ];
    }

    public function test_a_line_can_be_appended_to_an_existing_sale(): void
    {
        $sale = $this->sale();
        $item = $this->stockedItem('Rice');

        $response = $this->actingAs($this->user())
            ->postJson("/api/sales/{$sale->id}/lines", $this->linePayload($item));

        $response->assertCreated()
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.total', 14000);

        $this->assertDatabaseHas('sale_lines', [
            'sale_id' => $sale->id,
            'item_id' => $item->id,
            'unit_price' => 4000,
        ]);
    }

    public function test_appending_a_line_keeps_it_in_the_same_sale_group(): void
    {
        $sale = $this->sale();
        $item = $this->stockedItem('Rice');
        $salesBefore = Sale::count();

        $this->actingAs($this->user())
            ->postJson("/api/sales/{$sale->id}/lines", $this->linePayload($item))
            ->assertCreated();

        $this->assertSame($salesBefore, Sale::count());
        $this->assertSame(2, $sale->fresh()->lines()->count());
    }

    public function test_an_item_without_stock_cannot_be_added(): void
    {
        $sale = $this->sale();
        $item = $this->item('Salt');

        $this->actingAs($this->user())
            ->postJson("/api/sales/{$sale->id}/lines", $this->linePayload($item))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_an_unknown_item_cannot_be_added(): void
    {
        $sale = $this->sale();

        $this->actingAs($this->user())
            ->postJson("/api/sales/{$sale->id}/lines", $this->linePayload($this->stockedItem('Rice'), [
                'item_id' => 999999,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.item_id']);
    }

    public function test_a_purchase_cannot_receive_a_sale_line(): void
    {
        $item = $this->stockedItem();
        $line = $this->recordLine($item, 'purchase', 2, 'bag', 3000, '2026-10-24');
        $purchase = $line->transaction()->firstOrFail();

        $this->actingAs($this->user())
            ->postJson("/api/sales/{$purchase->id}/lines", $this->linePayload($item))
            ->assertNotFound();

        $this->assertSame(1, $purchase->fresh()->lines()->count());
    }

    public function test_another_tenants_sale_is_not_found(): void
    {
        $other = Tenant::factory()->create();
        $branch = Branch::factory()->for($other)->create(['is_default' => true, 'is_active' => true]);
        $foreignItem = Item::create([
            'tenant_id' => $other->id,
            'name' => 'Foreign',
            'name_key' => Item::nameKey('Foreign'),
            'family' => 'count',
            'default_unit_code' => 'bag',
            'is_active' => true,
        ]);
        $line = $this->recordLine($foreignItem, 'sale', 2, 'bag', 5000, '2026-10-24', null, $branch);
        $sale = $line->transaction()->firstOrFail();

        $item = $this->stockedItem('Rice');

        $this->actingAs($this->user())
            ->postJson("/api/sales/{$sale->id}/lines", $this->linePayload($item))
            ->assertNotFound();
    }

    public function test_appending_a_line_requires_authentication(): void
    {
        $sale = $this->sale();
        $item = $this->stockedItem('Rice');

        $this->post("/api/sales/{$sale->id}/lines", $this->linePayload($item))
            ->assertRedirect('/login');
    }
}
