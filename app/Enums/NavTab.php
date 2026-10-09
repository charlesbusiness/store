<?php

namespace App\Enums;

/**
 * The four tabs of the app, in the order they appear in the navigation.
 *
 * Catalogue screens (items and units) are deliberately not tabs: they are
 * supporting tools, linked from the footer, so the primary navigation stays as
 * compact as the original.
 */
enum NavTab: string
{
    case Reports = 'reports';
    case Sell = 'sell';
    case Buy = 'buy';
    case Stock = 'stock';

    public function label(): string
    {
        return match ($this) {
            self::Reports => 'Reports',
            self::Sell => 'Sell',
            self::Buy => 'Buy',
            self::Stock => 'Stock',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Reports => 'reports.index',
            self::Sell => 'sales.create',
            self::Buy => 'purchases.create',
            self::Stock => 'stock.index',
        };
    }
}
