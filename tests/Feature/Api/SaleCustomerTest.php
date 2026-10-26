<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Transaction;
use Tests\TestCase;

class SaleCustomerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function stockedItem(string $name = 'Yam'): Item
    {
        $item = $this->item($name);
        $this->recordLine($item, 'purchase', 10, 'bag', 3000, '2026-10-20');

        return $item;
    }

    private function sale(?string $customerName = null): Transaction
    {
        $item = $this->item('Yam');
        $line = $this->recordLine($item, 'sale', 2, 'bag', 5000, '2026-10-24');
        $sale = $line->transaction()->firstOrFail();

        if ($customerName !== null) {
            $sale->customerName()->create(['name' => $customerName]);
        }

        return $sale;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function salePayload(Item $item, array $overrides = []): array
    {
        return array_merge([
            'transacted_on' => '2026-10-24',
            'lines' => [
                [
                    'item_id' => $item->id,
                    'qty' => 2,
                    'unit_code' => 'bag',
                    'unit_price' => 5000,
                ],
            ],
        ], $overrides);
    }

    public function test_a_sale_records_a_customer_name(): void
    {
        $item = $this->stockedItem();

        $response = $this->actingAs($this->user())
            ->postJson('/api/sales', $this->salePayload($item, ['customer_name' => ' Ada Obi ']));

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', 'Ada Obi');

        $this->assertDatabaseHas('customer_names', [
            'transaction_id' => $response->json('data.id'),
            'name' => 'Ada Obi',
        ]);
    }

    public function test_a_sale_can_be_recorded_without_a_customer_name(): void
    {
        $item = $this->stockedItem();

        $response = $this->actingAs($this->user())
            ->postJson('/api/sales', $this->salePayload($item));

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', null);

        $this->assertDatabaseCount('customer_names', 0);
    }

    public function test_a_blank_customer_name_is_not_stored(): void
    {
        $item = $this->stockedItem();

        $response = $this->actingAs($this->user())
            ->postJson('/api/sales', $this->salePayload($item, ['customer_name' => '   ']));

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', null);

        $this->assertDatabaseCount('customer_names', 0);
    }

    public function test_a_customer_name_is_limited_to_a_sensible_length(): void
    {
        $item = $this->stockedItem();

        $this->actingAs($this->user())
            ->postJson('/api/sales', $this->salePayload($item, [
                'customer_name' => str_repeat('a', 121),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name']);
    }

    public function test_a_customer_name_can_be_added_to_an_existing_sale(): void
    {
        $sale = $this->sale();

        $response = $this->actingAs($this->user())
            ->patchJson("/api/sales/{$sale->id}/customer", ['customer_name' => 'Ada Obi']);

        $response->assertOk()
            ->assertJsonPath('data.customer_name', 'Ada Obi');

        $this->assertDatabaseHas('customer_names', [
            'transaction_id' => $sale->id,
            'name' => 'Ada Obi',
        ]);
    }

    public function test_updating_a_customer_name_reuses_the_existing_row(): void
    {
        $sale = $this->sale(customerName: 'Ada Obi');

        $this->actingAs($this->user())
            ->patchJson("/api/sales/{$sale->id}/customer", ['customer_name' => 'Grace Eze'])
            ->assertOk()
            ->assertJsonPath('data.customer_name', 'Grace Eze');

        $this->assertDatabaseCount('customer_names', 1);
        $this->assertDatabaseHas('customer_names', [
            'transaction_id' => $sale->id,
            'name' => 'Grace Eze',
        ]);
    }

    public function test_a_customer_name_can_be_cleared_from_a_sale(): void
    {
        $sale = $this->sale(customerName: 'Ada Obi');

        $this->actingAs($this->user())
            ->patchJson("/api/sales/{$sale->id}/customer", ['customer_name' => null])
            ->assertOk()
            ->assertJsonPath('data.customer_name', null);

        $this->assertDatabaseCount('customer_names', 0);
    }

    public function test_updating_a_customer_name_validates_its_length(): void
    {
        $sale = $this->sale();

        $this->actingAs($this->user())
            ->patchJson("/api/sales/{$sale->id}/customer", [
                'customer_name' => str_repeat('a', 121),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name']);
    }

    public function test_a_customer_name_update_requires_authentication(): void
    {
        $sale = $this->sale();

        $this->patch("/api/sales/{$sale->id}/customer", ['customer_name' => 'Ada Obi'])
            ->assertRedirect('/login');
    }

    public function test_a_purchase_cannot_have_a_customer_name(): void
    {
        $item = $this->item('Yam');
        $line = $this->recordLine($item, 'purchase', 2, 'bag', 5000, '2026-10-24');
        $purchase = $line->transaction()->firstOrFail();

        $this->actingAs($this->user())
            ->patchJson("/api/sales/{$purchase->id}/customer", ['customer_name' => 'Ada Obi'])
            ->assertNotFound();

        $this->assertDatabaseCount('customer_names', 0);
    }

    public function test_deleting_a_sale_deletes_its_customer_name(): void
    {
        $sale = $this->sale(customerName: 'Ada Obi');

        $sale->delete();

        $this->assertDatabaseCount('customer_names', 0);
    }
}
