<?php

namespace App\Support\Concerns;

use Illuminate\Support\Number;

/**
 * Display formatting for money, quantities and labels.
 *
 * Methods are static so the trait is usable three ways: on any class that
 * `use`s it, on classes that extend the abstract Service, and through the
 * Blade @money / @qty / @plural / @percent directives.
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
     * Format a quantity with thousands separators and at most 3 decimals.
     */
    public static function qty(int|float|string|null $quantity): string
    {
        return self::plain($quantity, (int) config('stockly.quantity_decimals', 3));
    }

    /**
     * Format a bare number -- no currency, no suffix -- with a maximum number
     * of decimals. This is the shared engine behind money() and qty().
     */
    public static function plain(int|float|string|null $number, int $maxDecimals = 2): string
    {
        if ($number === null || $number === '') {
            return '0';
        }

        return Number::format((float) $number, maxPrecision: $maxDecimals, locale: 'en');
    }

    /**
     * Format a 0-1 ratio as a percentage, e.g. for chart scaling.
     */
    public static function percent(int|float $ratio, int $decimals = 0): string
    {
        return Number::percentage($ratio, maxPrecision: $decimals, locale: 'en');
    }

    /**
     * Naive English pluralisation: 1 basket, 2 baskets. Pass the plural
     * explicitly for irregular words.
     */
    public static function plural(int|float $count, string $singular, ?string $plural = null): string
    {
        $count = (float) $count;
        $word = $count == 1 ? $singular : ($plural ?? $singular.'s');

        return self::plain($count, 0).' '.$word;
    }

    /**
     * Trim a float for echoing back into an <input> value.
     */
    public static function inputValue(int|float|string|null $number): string
    {
        if ($number === null || $number === '') {
            return '';
        }

        $number = (float) $number;

        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }
}
