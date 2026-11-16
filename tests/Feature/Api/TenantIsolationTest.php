<?php

namespace Tests\Feature\Api;

use App\Enums\ReportingMode;
use App\Enums\StockManagementMode;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
    }

    private function otherTenant(): array
    {
        $tenant = Tenant::create([
            'name' => 'Other',
            'stock_management_mode' => StockManagementMode::Account,
            'reporting_mode' => ReportingMode::Account,
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Other Main',
            'code' => 'other-main',
            'is_default' => true,
            'is_active' => true,
        ]);

        return [$tenant, $branch];
    }

    public function test_an_item_from_another_account_is_not_visible(): void
    {
        [$other] = $this->otherTenant();
        $foreign = Item::factory()->create([
            'tenant_id' => $other->id,
            'name' => 'Foreign Item',
            'default_unit_code' => 'bag',
        ]);

        $headers = $this->withTenant($this->user());

        $this->withHeaders($headers)->getJson("/api/items/{$foreign->id}")->assertNotFound();
        $this->withHeaders($headers)->patchJson("/api/items/{$foreign->id}", ['name' => 'Renamed'])->assertNotFound();
        $this->withHeaders($headers)->deleteJson("/api/items/{$foreign->id}")->assertNotFound();
    }

    public function test_item_names_are_unique_per_account_not_globally(): void
    {
        [$other] = $this->otherTenant();
        Item::factory()->create([
            'tenant_id' => $other->id,
            'name' => 'Rice',
            'default_unit_code' => 'bag',
        ]);

        $headers = $this->withTenant($this->user());

        $this->withHeaders($headers)
            ->postJson('/api/items', [
                'name' => ' rice ',
                'family' => 'count',
                'default_unit_code' => 'bag',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'rice');
    }

    public function test_a_receipt_from_another_account_cannot_be_downloaded(): void
    {
        [$other, $branch] = $this->otherTenant();
        $sale = Sale::create([
            'tenant_id' => $other->id,
            'branch_id' => $branch->id,
            'transacted_on' => '2026-10-24',
        ]);

        $this->withHeaders($this->withTenant($this->user()))
            ->get("/api/sales/{$sale->id}/receipt")
            ->assertNotFound();
    }

    public function test_a_customer_name_records_the_account(): void
    {
        $item = $this->item('Yam');
        $this->recordLine($item, 'purchase', 10, 'bag', 3000, '2026-10-20');

        $response = $this->actingAs($this->user())
            ->postJson('/api/sales', [
                'transacted_on' => '2026-10-24',
                'customer_name' => 'Ada Obi',
                'lines' => [['item_id' => $item->id, 'qty' => 2, 'unit_code' => 'bag', 'unit_price' => 5000]],
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('customer_names', [
            'tenant_id' => $this->tenant()->id,
            'sale_id' => $response->json('data.id'),
        ]);
    }

    public function test_uploads_are_confined_to_the_account_folder(): void
    {
        Storage::fake('local');
        $headers = array_merge($this->withTenant($this->user()), ['Accept' => 'application/json']);

        $response = $this->withHeaders($headers)->post('/api/upload', [
            'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
            'path' => 'receipts',
        ]);

        $response->assertOk();
        $path = $response->json('data.path');
        $this->assertStringStartsWith('tenants/'.$this->tenant()->id.'/uploads/receipts/', $path);

        $this->withHeaders($headers)
            ->deleteJson('/api/upload', ['path' => 'tenants/999/uploads/secret.pdf'])
            ->assertNotFound();

        $this->withHeaders($headers)
            ->post('/api/upload', [
                'file' => UploadedFile::fake()->create('report.pdf', 10),
                'path' => '../escape',
            ])
            ->assertUnprocessable();
    }

    public function test_units_are_read_only_over_the_api(): void
    {
        $headers = $this->withTenant($this->user());

        $this->withHeaders($headers)->getJson('/api/units')->assertOk();
        $this->withHeaders($headers)
            ->postJson('/api/units', ['code' => 'nope', 'label' => 'Nope'])
            ->assertStatus(405);
    }

    public function test_stock_traded_only_filter_does_not_crash(): void
    {
        $item = $this->item('Yam');
        $this->recordLine($item, 'purchase', 10, 'bag', 3000, '2026-10-20');

        $this->withHeaders($this->withTenant($this->user()))
            ->getJson('/api/stock?traded_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Yam');
    }
}
