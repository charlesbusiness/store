<?php

namespace App\Enums;

/**
 * The two kinds of ledger entry. They are mirror images of each other: a
 * purchase states the total cost paid, a sale states the price per unit.
 */
enum RecordType: string
{
    case Sale = 'sale';
    case Purchase = 'purchase';

    /** Purchases add to stock, sales remove from it. */
    public function direction(): int
    {
        return $this === self::Purchase ? 1 : -1;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function plural(): string
    {
        return $this === self::Purchase ? 'purchases' : 'sales';
    }

    public function routeName(): string
    {
        return $this === self::Purchase ? 'purchases.' : 'sales.';
    }

    /** Purchases may introduce a brand new item; sales may not. */
    public function allowsNewItems(): bool
    {
        return $this === self::Purchase;
    }

    /**
     * Sales are restricted to items that currently have stock.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
