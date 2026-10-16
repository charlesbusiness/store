<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Enums\UnitFamily;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $family = $this->faker->randomElement(UnitFamily::cases());
        $code = 'pcs-' . $family->value;
        
        return [
            'code' => $code,
            'label' => 'Piece',
            'symbol' => 'pcs',
            'plural' => 'pieces',
            'family' => $family,
            'base_multiplier' => 1,
            'is_fraction' => false,
            'is_decimal' => false,
            'is_default' => true,
            'is_active' => true,
            'sort' => 1,
        ];
    }
}
