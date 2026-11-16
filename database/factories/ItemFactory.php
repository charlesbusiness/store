<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\Unit;
use App\Enums\UnitFamily;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        $family = $this->faker->randomElement(UnitFamily::cases());

        return [
            'name' => $name,
            'name_key' => Item::nameKey($name),
            'family' => $family,
            'default_unit_code' => 'pcs-' . $family->value,
            'pack_size' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (Item $item) {
            if ($item->name) {
                $item->name = Item::normaliseName($item->name);
                $item->name_key = Item::nameKey($item->name);
            }

            if (! $item->tenant_id) {
                try {
                    $item->tenant_id = app(TenantContext::class)->tenantId();
                } catch (\Throwable) {
                    $item->tenant_id = Tenant::factory()->create()->id;
                }
            }
        })->afterCreating(function (Item $item) {
            if (!$item->unit) {
                Unit::firstOrCreate(
                    [
                        'code' => 'pcs-' . $item->family->value,
                        'family' => $item->family,
                    ],
                    [
                        'label' => 'Piece',
                        'symbol' => 'pcs',
                        'plural' => 'pieces',
                        'base_multiplier' => 1,
                        'is_fraction' => false,
                        'is_decimal' => false,
                        'is_default' => true,
                        'is_active' => true,
                        'sort' => 1,
                    ]
                );
            }
        });
    }
}
