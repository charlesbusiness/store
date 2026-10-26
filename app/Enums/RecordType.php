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

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Purchases may introduce a brand new item; sales may not. */
    public function allowsNewItems(): bool
    {
        return $this === self::Purchase;
    }
}
