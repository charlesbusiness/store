<?php

namespace App\Support;

use App\Models\Unit;

/**
 * What one trading unit is made of, as declared on a purchase.
 *
 * A carton of thyme declared as 12 rolls and 24 sachets per carton is
 * Packs(['roll' => 12, 'sachet' => 24]). The declaration belongs to the receipt
 * that carried it, and is kept on the line so history keeps the meaning it was
 * written with.
 *
 * Declaring packs does not change what stock is stored in: one carton is still
 * one piece of the item, exactly as before. What the declaration buys is the
 * ability to enter a fraction that divides cleanly into whole inner packages --
 * half a carton of 12 is 6 rolls -- and to show the breakdown afterwards.
 */
final class Packs
{
    /** @param  array<string, array{per: float, cost?: float|null, sellingPrice?: float|null}>  $perPack  inner unit code => count and optional cost/selling price per inner unit */
    private function __construct(private readonly array $perPack = []) {}

    /** @param  array<int|string, mixed>|null  $declared */
    public static function fromArray(?array $declared): self
    {
        $perPack = [];

        foreach ($declared ?? [] as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $unit = trim((string) ($slot['unit'] ?? ''));
            $count = (float) ($slot['per'] ?? 0);
            $cost = isset($slot['cost']) && $slot['cost'] !== '' && $slot['cost'] !== null
                ? (float) $slot['cost']
                : null;
            $sellingPrice = isset($slot['sellingPrice']) && $slot['sellingPrice'] !== '' && $slot['sellingPrice'] !== null
                ? (float) $slot['sellingPrice']
                : null;

            // A half-typed pair is simply not declared: the field is optional.
            if ($unit !== '' && $count > 0) {
                $perPack[$unit] = ['per' => $count, 'cost' => $cost, 'sellingPrice' => $sellingPrice];
            }
        }

        return new self($perPack);
    }

    public static function none(): self
    {
        return new self;
    }

    /**
     * The pairs as the form submitted them, ready to be stored. Blank slots are
     * dropped rather than stored as nulls.
     *
     * @return array<int, array{unit: string, per: float, cost: float|null, sellingPrice: float|null}>
     */
    public function toArray(): array
    {
        $pairs = [];

        foreach ($this->perPack as $unit => $data) {
            $pairs[] = [
                'unit' => $unit,
                'per' => $data['per'],
                'cost' => $data['cost'],
                'sellingPrice' => $data['sellingPrice'] ?? null,
            ];
        }

        return $pairs;
    }

    public function isEmpty(): bool
    {
        return $this->perPack === [];
    }

    /** How many of $unit make one trading unit, or null when not declared. */
    public function per(Unit|string $unit): ?float
    {
        $code = $unit instanceof Unit ? $unit->code : $unit;
        return $this->perPack[$code]['per'] ?? null;
    }

    /** Cost per inner unit, or null when not declared or not provided. */
    public function costPerUnit(Unit|string $unit): ?float
    {
        $code = $unit instanceof Unit ? $unit->code : $unit;
        return $this->perPack[$code]['cost'] ?? null;
    }

    /** Selling price per inner unit, or null when not declared or not provided. */
    public function sellingPricePerUnit(Unit|string $unit): ?float
    {
        $code = $unit instanceof Unit ? $unit->code : $unit;
        return $this->perPack[$code]['sellingPrice'] ?? null;
    }

    public function has(Unit|string $unit): bool
    {
        return $this->per($unit) !== null;
    }

    /**
     * "12 rolls or 24 sachets", for a validation message.
     *
     * @param  array<string, string>  $plurals  unit code => plural label
     */
    public function summary(array $plurals): string
    {
        $parts = [];

        foreach ($this->perPack as $unit => $data) {
            $parts[] = ValueFormatter::plain($data['per'], 2).' '.strtolower($plurals[$unit] ?? $unit);
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return implode(' or ', array_slice($parts, 0, -1)).' or '.end($parts);
    }
}
