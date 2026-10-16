<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Unit;
use App\Enums\UnitFamily;
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
            'name_key' => strtolower($name),
            'family' => $family,
            'default_unit_code' => 'pcs-' . $family->value,
            'pack_size' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Item $item) {
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
