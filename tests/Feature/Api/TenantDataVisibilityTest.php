<?php

namespace Tests\Feature\Api;

use App\Enums\ReportingMode;
use App\Enums\StockManagementMode;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Transaction;
use App\Models\TransactionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDataVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_user_can_see_migrated_transactions_and_reports(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create([
            'name' => 'Account',
            'stock_management_mode' => StockManagementMode::Account,
            'reporting_mode' => ReportingMode::Account,
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'code' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);
        $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);
        $branch->users()->attach($user);
        Transaction::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'type' => 'purchase',
            'transacted_on' => now()->toDateString(),
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenant->id];

        $this->withHeaders($headers)->getJson('/api/purchases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['trace']);
        $this->withHeaders($headers)->getJson('/api/reports')->assertOk();
    }

    public function test_member_can_only_see_and_use_assigned_branches(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Account', 'stock_management_mode' => StockManagementMode::Account, 'reporting_mode' => ReportingMode::Account]);
        $main = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'is_default' => true, 'is_active' => true]);
        $second = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Second', 'code' => 'second', 'is_active' => true]);
        $tenant->users()->attach($user, ['role' => 'member', 'is_active' => true]);
        $main->users()->attach($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonCount(1, 'branches')
            ->assertJsonFragment(['code' => 'main'])
            ->assertJsonMissing(['code' => 'second']);

        $headers = ['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenant->id];

        $this->withHeaders($headers)->getJson('/api/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders([...$headers, 'X-Branch-ID' => $second->id])
            ->getJson('/api/purchases')
            ->assertForbidden();
    }

    public function test_owner_can_see_and_use_every_branch_in_their_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Account', 'stock_management_mode' => StockManagementMode::Account, 'reporting_mode' => ReportingMode::Account]);
        $main = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'is_default' => true, 'is_active' => true]);
        $second = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Second', 'code' => 'second', 'is_active' => true]);
        $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonCount(2, 'branches');

        $headers = ['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenant->id];

        $this->withHeaders($headers)->getJson('/api/branches')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withHeaders([...$headers, 'X-Branch-ID' => $second->id])
            ->getJson('/api/purchases')
            ->assertOk();
    }

    public function test_owner_can_create_and_disable_a_branch(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Account', 'stock_management_mode' => StockManagementMode::Account, 'reporting_mode' => ReportingMode::Account]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'is_default' => true, 'is_active' => true]);
        $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);
        $branch->users()->attach($user);
        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenant->id, 'X-Branch-ID' => $branch->id];

        $created = $this->withHeaders($headers)->postJson('/api/branches', ['name' => 'Second', 'code' => 'second'])->assertCreated();
        $created->assertJsonPath('data.code', 'second');
        $second = Branch::where('code', 'second')->firstOrFail();

        $this->withHeaders($headers)->deleteJson("/api/branches/{$second->id}")->assertOk();
        $this->assertDatabaseHas('branches', ['id' => $second->id, 'is_active' => 0]);
    }

    public function test_member_cannot_create_a_branch(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Account', 'stock_management_mode' => StockManagementMode::Account, 'reporting_mode' => ReportingMode::Account]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'is_default' => true, 'is_active' => true]);
        $tenant->users()->attach($user, ['role' => 'member', 'is_active' => true]);
        $branch->users()->attach($user);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenant->id, 'X-Branch-ID' => $branch->id])
            ->postJson('/api/branches', ['name' => 'Nope', 'code' => 'nope'])
            ->assertForbidden();
    }

    public function test_owner_can_transfer_stock_between_branches(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::create(['name' => 'Account', 'stock_management_mode' => StockManagementMode::Branch, 'reporting_mode' => ReportingMode::Branch]);
        $source = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'is_default' => true, 'is_active' => true]);
        $destination = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Second', 'code' => 'second', 'is_active' => true]);
        $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true]);
        $source->users()->attach($user);
        $destination->users()->attach($user);
        $unit = Unit::factory()->create(['code' => 'transfer-pcs', 'family' => 'count', 'base_multiplier' => 1]);
        $item = Item::create(['tenant_id' => $tenant->id, 'name' => 'Transfer item', 'name_key' => 'transfer item', 'family' => 'count', 'default_unit_code' => $unit->code, 'is_active' => true]);
        $transaction = Transaction::create(['tenant_id' => $tenant->id, 'branch_id' => $source->id, 'type' => 'purchase', 'transacted_on' => now()->toDateString()]);
        TransactionLine::create(['transaction_id' => $transaction->id, 'item_id' => $item->id, 'qty' => 5, 'unit_code' => $unit->code, 'base_qty' => 5, 'unit_price' => 10, 'total' => 50]);
        $token = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenant->id, 'X-Branch-ID' => $source->id];

        $response = $this->withHeaders($headers)->postJson('/api/transfers', [
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'transferred_on' => now()->toDateString(),
            'lines' => [['item_id' => $item->id, 'qty' => 2, 'unit_code' => $unit->code]],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('stock_transfer_lines', ['item_id' => $item->id, 'base_qty' => 2]);
    }
}
