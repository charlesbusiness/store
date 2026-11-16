<?php

namespace Tests\Feature\Api;

use App\Enums\ReportingMode;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\Stock\StockTransferService;
use Tests\TestCase;

class ReportScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function twoBranchSetup(): array
    {
        $tenant = $this->tenant();
        $a = $this->branch();
        $b = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Second',
            'code' => 'second',
            'is_active' => true,
        ]);
        $this->context()->set($tenant, $a);

        $owner = $this->user();
        $item = $this->item('Rice');

        $this->recordLine($item, 'sale', 1, 'bag', 1000, '2026-10-07', null, $a);
        $this->recordLine($item, 'sale', 2, 'bag', 5000, '2026-10-07', null, $b);
        $this->recordLine($item, 'purchase', 10, 'bag', 1000, '2026-10-07', null, $a);
        $this->recordLine($item, 'purchase', 4, 'bag', 500, '2026-10-07', null, $b);

        return [$tenant, $a, $b, $owner, $item];
    }

    public function test_account_scope_totals_every_branch_and_breaks_them_down(): void
    {
        [$tenant, $a, $b, $owner] = $this->twoBranchSetup();

        $response = $this->withHeaders($this->withTenant($owner, $tenant, $a))
            ->getJson('/api/reports?mode=d&anchor=2026-10-07&scope=account')
            ->assertOk();

        $response->assertJsonPath('data.scope', 'account')
            ->assertJsonPath('data.sales', 11000)
            ->assertJsonPath('data.purchases', 1500)
            ->assertJsonCount(2, 'data.branchBreakdown');

        $rows = collect($response->json('data.branchBreakdown'))->keyBy('branch_id');
        $this->assertSame(1000.0, (float) $rows[$a->id]['sales']);
        $this->assertSame(10000.0, (float) $rows[$b->id]['sales']);
        $this->assertFalse($rows[$a->id]['is_selected']);
        $this->assertFalse($rows[$b->id]['is_selected']);
    }

    public function test_branch_scope_reports_one_branch_without_a_breakdown(): void
    {
        [$tenant, $a, $b, $owner] = $this->twoBranchSetup();

        $response = $this->withHeaders($this->withTenant($owner, $tenant, $b))
            ->getJson('/api/reports?mode=d&anchor=2026-10-07&scope=branch')
            ->assertOk();

        $response->assertJsonPath('data.scope', 'branch')
            ->assertJsonPath('data.sales', 10000)
            ->assertJsonPath('data.purchases', 500)
            ->assertJsonCount(0, 'data.branchBreakdown');
    }

    public function test_account_scope_stock_value_sums_branches_even_in_branch_stock_mode(): void
    {
        [$tenant, $a, , $owner, $item] = $this->twoBranchSetup();
        $tenant->update(['stock_management_mode' => \App\Enums\StockManagementMode::Branch]);
        $this->context()->set($tenant, $a);

        // Account-wide: 14 bought for 1,500, 3 sold, so 11 left at 107.14 each.
        $this->withHeaders($this->withTenant($owner, $tenant, $a))
            ->getJson('/api/reports?mode=d&anchor=2026-10-07&scope=account')
            ->assertOk()
            ->assertJsonPath('data.stockValue', 1178.5714285714284);
    }

    public function test_transfer_cost_reduces_source_and_increases_destination_branch_purchases_but_is_neutral_account_wide(): void
    {
        [$tenant, $source, $destination, $owner, $item] = $this->twoBranchSetup();
        $tenant->update(['stock_management_mode' => \App\Enums\StockManagementMode::Branch]);
        $owner->branches()->syncWithoutDetaching([$destination->id]);
        $this->recordLine($item, 'purchase', 10, 'bag', 1000, '2026-10-07', null, $source);

        app(StockTransferService::class)->create([
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'transferred_on' => '2026-10-07',
            'lines' => [['item_id' => $item->id, 'qty' => 4, 'unit_code' => 'bag']],
        ], $owner->id);

        $sourceReport = $this->withHeaders($this->withTenant($owner, $tenant, $source))
            ->getJson('/api/reports?mode=d&anchor=2026-10-07&scope=branch')
            ->assertOk();
        $destinationReport = $this->withHeaders($this->withTenant($owner, $tenant, $destination))
            ->getJson('/api/reports?mode=d&anchor=2026-10-07&scope=branch')
            ->assertOk();
        $accountReport = $this->withHeaders($this->withTenant($owner, $tenant, $source))
            ->getJson('/api/reports?mode=d&anchor=2026-10-07&scope=account')
            ->assertOk();

        $this->assertSame(1600.0, (float) $sourceReport->json('data.purchases'));
        $this->assertSame(1300.0, (float) $destinationReport->json('data.purchases'));
        $this->assertSame(2500.0, (float) $accountReport->json('data.purchases'));
    }
}
