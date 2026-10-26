<?php

namespace App\Support;

use App\Support\Concerns\FormatsValues;
use Illuminate\Support\Number;

/**
 * Static bridge into the formatting trait for contexts where there is no
 * service instance to call instance methods on.
 */
class ValueFormatter
{
    use FormatsValues;

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
