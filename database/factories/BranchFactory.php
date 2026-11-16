<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->unique()->city().' Branch',
            'code' => strtoupper($this->faker->unique()->bothify('BR-####')),
            'created_by' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
