<?php

namespace App\Enums;

/**
 * A family is a dimension of measurement. Every unit belongs to exactly one
 * family and carries a multiplier relative to that family's base unit, which
 * is why stock is always stored in base units and never mixes dimensions.
 */
enum UnitFamily: string
{
    case Count = 'count';
    case Weight = 'weight';
    case Volume = 'volume';
    case Length = 'length';
    case Area = 'area';

    /** The unit a family measures in; all multipliers are relative to one of it. */
    public function baseUnitCode(): string
    {
        return match ($this) {
            self::Count => 'pcs',
            self::Weight => 'g',
            self::Volume => 'ml',
            self::Length => 'cm',
            self::Area => 'sqcm',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Count => 'Pieces & containers',
            self::Weight => 'Weight',
            self::Volume => 'Volume',
            self::Length => 'Length',
            self::Area => 'Area',
        };
    }

    /** Families whose base unit is a physical amount rather than a discrete object. */
    public function isMeasurable(): bool
    {
        return $this !== self::Count;
    }

    /**
     * Quantities in a measurable family are naturally fractional (1.25 kg),
     * while a count family only accepts whole units -- traders buy "2 half"
     * rather than "2.5 pieces".
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $family) => [$family->value => $family->label()])
            ->all();
    }

    /**
     * The families as a list of objects, for a browser to iterate.
     *
     * options() is keyed by the value, which is what a <select> that posts back
     * wants -- but keyed means an object once it is JSON, and x-for over an object
     * yields nothing a template can bind. A list of {value, label} pairs survives
     * the trip intact and lets the markup bind both halves.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function choices(): array
    {
        return array_map(
            fn (self $family) => ['value' => $family->value, 'label' => $family->label()],
            self::cases(),
        );
    }
}
