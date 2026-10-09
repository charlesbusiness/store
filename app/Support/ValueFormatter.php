<?php

namespace App\Support;

use App\Enums\UnitFamily;
use App\Models\Unit;
use App\Support\Concerns\FormatsValues;
use Illuminate\Support\Number;

/**
 * Static bridge into the formatting trait for Blade and console contexts,
 * where there is no service instance to call instance methods on.
 *
 * Views use the @money, @qty, @unit, @plural, @percent and @input directives
 * registered in AppServiceProvider, all of which land here.
 */
class ValueFormatter
{
    use FormatsValues;

    /**
     * The short symbol a unit is written as next to a quantity, e.g. "kg".
     * Falls back to the code so a label is never empty.
     */
    public static function unit(Unit|UnitFamily|string|null $unit): string
    {
        if ($unit === null) {
            return '';
        }

        if (is_string($unit)) {
            return $unit;
        }

        if ($unit instanceof UnitFamily) {
            return $unit->label();
        }

        return $unit->symbol ?: $unit->code;
    }

    /**
     * The display unit for a quantity, with a safe fallback for items that
     * have not traded yet.
     */
    public static function displayUnit(Unit|string|null $unit): string
    {
        if ($unit === null) {
            return 'units';
        }

        return is_string($unit) ? $unit : ($unit->symbol ?: $unit->code);
    }

    /**
     * Scale a value against a maximum for the CSS bar chart, clamped so a
     * zero-maximum chart still renders flat bars instead of dividing by zero.
     */
    public static function barHeight(int|float $value, int|float $max): string
    {
        $max = max(1.0, (float) $max);

        return Number::format(max(0, (float) $value / $max * 100), maxPrecision: 4, locale: 'en').'%';
    }
}
