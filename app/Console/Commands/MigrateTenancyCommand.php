<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTenancyCommand extends Command
{
    protected $signature = 'stockly:migrate-tenancy
        {--tenant= : Existing tenant ID to use}
        {--owner= : Existing user ID to own the default tenant and branch}
        {--tenant-name=My Account : Name for a newly created tenant}
        {--branch-name=Main Branch : Name for the default branch}
        {--branch-code=default : Code for the default branch}
        {--dry-run : Show the records that would be changed without writing}';

    protected $description = 'Backfill existing Stockly data into a tenant and default branch';

    public function handle(): int
    {
        $owner = $this->resolveOwner();
        $tenant = $this->resolveTenant();
        $branch = $tenant->branches()->where('code', $this->option('branch-code'))->first();

        $counts = [
            'items' => DB::table('items')->whereNull('tenant_id')->count(),
            'transactions' => DB::table('transactions')->where(fn ($query) => $query->whereNull('tenant_id')->orWhereNull('branch_id'))->count(),
            'customers' => DB::table('customer_names')->whereNull('tenant_id')->count(),
        ];

        $this->table(['Value', 'ID'], [
            ['Owner', $owner->id],
            ['Tenant', $tenant->id],
            ['Default branch', $branch?->id ?? 'will be created'],
        ]);
        $this->table(['Table', 'Rows needing backfill'], array_map(
            fn (string $table, int $count) => [$table, $count],
            array_keys($counts),
            array_values($counts),
        ));

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($owner, $tenant, &$branch): void {
            $branch ??= $tenant->branches()->create([
                'name' => $this->option('branch-name'),
                'code' => $this->option('branch-code'),
                'created_by' => $owner->id,
                'is_default' => true,
                'is_active' => true,
            ]);

            if (! $branch->is_default) {
                $branch->update(['is_default' => true]);
            }

            $tenant->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner', 'is_active' => true],
            ]);
            $branch->users()->syncWithoutDetaching([$owner->id]);

            DB::table('items')->whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);
            DB::table('transactions')->whereNull('tenant_id')->update([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
            ]);
            DB::table('transactions')->whereNull('branch_id')->update(['branch_id' => $branch->id]);
            DB::table('customer_names')->whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);
        });

        $this->info('Tenancy backfill completed successfully.');

        return self::SUCCESS;
    }

    private function resolveOwner(): User
    {
        $owner = $this->option('owner')
            ? User::query()->find((int) $this->option('owner'))
            : User::query()->orderBy('id')->first();

        if (! $owner) {
            $this->error('No user exists. Create a user before running this command.');
            abort(1);
        }

        return $owner;
    }

    private function resolveTenant(): Tenant
    {
        if ($id = $this->option('tenant')) {
            $tenant = Tenant::query()->find((int) $id);

            if (! $tenant) {
                $this->error("Tenant [{$id}] was not found.");
                abort(1);
            }

            return $tenant;
        }

        return Tenant::query()->firstOrCreate(
            [],
            [
                'name' => $this->option('tenant-name'),
                'stock_management_mode' => 'account',
                'reporting_mode' => 'account',
            ],
        );
    }
}
