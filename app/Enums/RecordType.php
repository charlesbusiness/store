<?php

namespace App\Enums;

use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Sale;
use App\Models\SaleLine;

/**
 * The two kinds of ledger entry. They are mirror images of each other: a
 * purchase states the total cost paid, a sale states the price per unit.
 *
 * Each side now has its own header and line tables, so the enum also maps a
 * type to the models that store it. Shared code that used to filter one table
 * by a `type` column asks the enum which models to read instead.
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

    /** @return class-string<LedgerEntry> */
    public function entryModel(): string
    {
        return match ($this) {
            self::Sale => Sale::class,
            self::Purchase => Purchase::class,
        };
    }

    /** @return class-string<LedgerLine> */
    public function lineModel(): string
    {
        return match ($this) {
            self::Sale => SaleLine::class,
            self::Purchase => PurchaseLine::class,
        };
    }

    /** The foreign key a line uses to point back at its header. */
    public function foreignKey(): string
    {
        return match ($this) {
            self::Sale => 'sale_id',
            self::Purchase => 'purchase_id',
        };
    }
}
