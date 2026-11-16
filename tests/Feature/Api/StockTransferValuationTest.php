<?php

namespace Tests\Feature\Api;

use App\Enums\StockManagementMode;
use App\Models\Branch;
use App\Models\Transaction;
use App\Services\Stock\StockTransferService;
use Tests\TestCase;

class StockTransferValuationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    public function test_a_transfer_carries_the_source_average_cost_to_the_destination(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['stock_management_mode' => StockManagementMode::Branch]);
        $source = $this->branch();
        $destination = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Destination',
            'code' => 'destination',
            'is_active' => true,
        ]);
        $this->context()->set($tenant, $source);

        $owner = $this->user();
        $owner->branches()->syncWithoutDetaching([$destination->id]);

        $item = $this->item('Rice');
        $this->recordLine($item, 'purchase', 10, 'bag', 100, '2026-10-20', null, $source);

        $this->withHeaders($this->withTenant($owner, $tenant, $source))
            ->postJson('/api/transfers', [
                'source_branch_id' => $source->id,
                'destination_branch_id' => $destination->id,
                'transferred_on' => '2026-10-21',
                'lines' => [['item_id' => $item->id, 'qty' => 5, 'unit_code' => 'bag']],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'branch_id' => $destination->id,
            'stock_transfer_id' => 1,
            'type' => 'purchase',
        ]);

        $this->assertDatabaseHas('stock_transfer_lines', [
            'item_id' => $item->id,
            'base_qty' => 5,
            'total_cost' => 50,
        ]);

        $sourceStock = $this->withHeaders($this->withTenant($owner, $tenant, $source))
            ->getJson('/api/stock')
            ->assertOk()
            ->json('data.0');

        $destinationStock = $this->withHeaders($this->withTenant($owner, $tenant, $destination))
            ->getJson('/api/stock')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(5.0, (float) $sourceStock['stock']);
        $this->assertSame(50.0, (float) $sourceStock['totalValue']);
        $this->assertSame(5.0, (float) $destinationStock['stock']);
        $this->assertSame(50.0, (float) $destinationStock['totalValue']);
    }

    public function test_a_transfer_snapshots_cost_even_for_an_item_never_traded_at_the_destination(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['stock_management_mode' => StockManagementMode::Branch]);
        $source = $this->branch();
        $destination = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Destination',
            'code' => 'destination',
            'is_active' => true,
        ]);
        $this->context()->set($tenant, $source);

        $owner = $this->user();
        $owner->branches()->syncWithoutDetaching([$destination->id]);
        $item = $this->item('Beans');
        $this->recordLine($item, 'purchase', 4, 'bag', 80, '2026-10-20', null, $source);

        app(StockTransferService::class)->create([
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'transferred_on' => '2026-10-21',
            'lines' => [['item_id' => $item->id, 'qty' => 1, 'unit_code' => 'bag']],
        ], $owner->id);

        $this->withHeaders($this->withTenant($owner, $tenant, $destination))
            ->getJson('/api/stock')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Beans')
            ->assertJsonPath('data.0.stock', 1)
            ->assertJsonPath('data.0.totalValue', 20);
    }

    public function test_stock_endpoint_shows_only_the_selected_branch(): void
    {
        $tenant = $this->tenant();
        $tenant->update(['stock_management_mode' => StockManagementMode::Account]);
        $source = $this->branch();
        $destination = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Destination',
            'code' => 'destination',
            'is_active' => true,
        ]);
        $this->context()->set($tenant, $source);
        $owner = $this->user();
        $owner->branches()->syncWithoutDetaching([$destination->id]);
        $item = $this->item('Rice');
        $otherItem = $this->item('Beans');
        $this->recordLine($item, 'purchase', 5, 'bag', 100, '2026-10-20', null, $source);
        $this->recordLine($otherItem, 'purchase', 9, 'bag', 100, '2026-10-20', null, $destination);

        $this->withHeaders($this->withTenant($owner, $tenant, $source))
            ->getJson('/api/stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rice')
            ->assertJsonPath('data.0.stock', 5);

        $this->withHeaders($this->withTenant($owner, $tenant, $destination))
            ->getJson('/api/stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Beans')
            ->assertJsonPath('data.0.stock', 9);
    }
}
