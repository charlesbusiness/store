<?php

namespace App\Support\Concerns;

use Illuminate\Support\Number;

/**
 * Display formatting for money and bare numbers.
 *
 * Methods are static so the trait is usable on any class that `use`s it or on
 * classes that extend the abstract Service.
 */
trait FormatsValues
{
    /**
     * Format a money figure with the configured currency symbol, thousands
     * separators and at most 2 decimals.
     */
    public static function money(int|float|string|null $amount): string
    {
        return config('stockly.currency').self::plain($amount, (int) config('stockly.money_decimals', 2));
    }

    /**
     * Format a bare number -- no currency, no suffix -- with a maximum number
     * of decimals.
     */
    public static function plain(int|float|string|null $number, int $maxDecimals = 2): string
    {
        if ($number === null || $number === '') {
            return '0';
        }

        return Number::format((float) $number, maxPrecision: $maxDecimals, locale: 'en');
    }
}
