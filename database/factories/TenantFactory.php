<?php

namespace Database\Factories;

use App\Enums\ReportingMode;
use App\Enums\StockManagementMode;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'stock_management_mode' => StockManagementMode::Account,
            'reporting_mode' => ReportingMode::Account,
        ];
    }
}
