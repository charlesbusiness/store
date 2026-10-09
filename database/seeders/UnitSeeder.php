<?php

namespace Database\Seeders;

use App\Enums\UnitFamily;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Loads the unit catalogue from the data files, one file per family.
 *
 * Keeping the catalogue in data files rather than inline here keeps this
 * seeder short and makes the units easy to read and extend.
 */
class UnitSeeder extends Seeder
{
    /** Data file => the family every unit inside it belongs to. */
    private const GROUPS = [
        'count' => UnitFamily::Count,
        'weight' => UnitFamily::Weight,
        'volume' => UnitFamily::Volume,
        'length' => UnitFamily::Length,
        'area' => UnitFamily::Area,
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $file => $family) {
            $this->seedFile($file, $family);
        }
    }

    private function seedFile(string $file, UnitFamily $family): void
    {
        $rows = require database_path("data/units/{$file}.php");

        foreach ($rows as [$code, $label, $symbol, $plural, $multiplier, $decimal, $fraction, $sort]) {
            Unit::updateOrCreate(
                ['code' => $code],
                [
                    'label' => $label,
                    'symbol' => $symbol,
                    'plural' => $plural,
                    'family' => $family,
                    'base_multiplier' => $multiplier,
                    'is_decimal' => $decimal,
                    'is_fraction' => $fraction,
                    'is_active' => true,
                    'sort' => $sort,
                ]
            );
        }
    }
}
