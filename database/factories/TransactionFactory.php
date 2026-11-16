<?php

namespace Database\Factories;

use App\Enums\RecordType;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        [$tenantId, $branchId] = $this->context();

        return [
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'type' => RecordType::Sale,
            'transacted_on' => now()->toDateString(),
            'user_id' => null,
            'paid' => false,
        ];
    }

    private function context(): array
    {
        try {
            $context = app(TenantContext::class);

            return [$context->tenantId(), $context->branchId()];
        } catch (\Throwable) {
            $tenant = Tenant::factory()->create();
            $branch = Branch::factory()->for($tenant)->create(['is_default' => true]);

            return [$tenant->id, $branch->id];
        }
    }
}
