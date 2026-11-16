<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected ?Tenant $defaultTenant = null;
    protected ?Branch $defaultBranch = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTenant = Tenant::factory()->create();
        $this->defaultBranch = Branch::factory()->for($this->defaultTenant)->create([
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->context()->set($this->defaultTenant, $this->defaultBranch);
    }

    protected function context(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function tenant(): Tenant
    {
        return $this->defaultTenant ??= Tenant::factory()->create();
    }

    protected function branch(): Branch
    {
        return $this->defaultBranch ??= Branch::factory()->for($this->tenant())->create([
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Seed the unit catalogue, which every other test depends on.
     */
    protected function seedUnits(): void
    {
        $this->seed(UnitSeeder::class);
    }

    protected function user(): User
    {
        $user = User::factory()->create();

        $this->tenant()->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'is_active' => true],
        ]);
        $user->branches()->syncWithoutDetaching([$this->branch()->id]);

        return $user;
    }

    protected function memberOf(Tenant $tenant, array $branchIds, string $role = 'member'): User
    {
        $user = User::factory()->create();

        $tenant->users()->syncWithoutDetaching([
            $user->id => ['role' => $role, 'is_active' => true],
        ]);
        $user->branches()->syncWithoutDetaching($branchIds);

        return $user;
    }

    protected function withTenant(User $user, ?Tenant $tenant = null, ?Branch $branch = null): array
    {
        $tenant ??= $this->tenant();
        $branch ??= $this->branch();

        $this->app['auth']->forgetGuards();

        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Tenant-ID' => $tenant->id,
            'X-Branch-ID' => $branch->id,
        ];
    }

    protected function unit(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    /**
     * Create an item.
     */
    protected function item(
        string $name,
        string $family = 'count',
        string $unitCode = 'bag',
        array $attributes = [],
    ): Item {
        return Item::create(array_merge([
            'tenant_id' => $this->tenant()->id,
            'name' => $name,
            'name_key' => Item::nameKey($name),
            'family' => $family,
            'default_unit_code' => $unitCode,
            'is_active' => true,
        ], $attributes))->load('displayUnit');
    }

    /**
     * Write a single-line transaction straight to the ledger, bypassing the
     * recorders. Use these to arrange a history worth asserting against.
     */
    protected function recordLine(
        Item $item,
        string $type,
        float $qty,
        string $unitCode,
        float $amount,
        ?string $date = null,
        ?float $cost = null,
        ?Branch $branch = null,
    ): TransactionLine {
        $unit = $this->unit($unitCode);
        $baseQty = $qty * (float) $unit->base_multiplier;
        $isSale = $type === 'sale';

        $transaction = Transaction::create([
            'tenant_id' => $item->tenant_id,
            'branch_id' => ($branch ?? $this->branch())->id,
            'type' => $type,
            'transacted_on' => $date ?? Carbon::today()->toDateString(),
        ]);

        return TransactionLine::create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'qty' => $qty,
            'unit_code' => $unitCode,
            'base_qty' => $baseQty,
            'unit_price' => $isSale ? $amount : round($amount / $qty, 6),
            'total' => round($isSale ? $qty * $amount : $amount, 2),
            // Left null by default, which is what a line recorded before cost was
            // snapshotted looks like.
            'cost' => $cost,
        ]);
    }
}
