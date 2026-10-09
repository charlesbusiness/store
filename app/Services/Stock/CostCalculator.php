<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Support\Packs;
use App\Enums\RecordType;

/**
 * Calculates the cost per unit for any unit (catalogue or declared inner unit)
 * based on purchase history and declared packs.
 */
class CostCalculator
{
    public function __construct(
        private readonly PackReader $packReader,
        private readonly StockLedger $ledger,
        private readonly UnitCatalog $units,
    ) {}

    /**
     * Get the cost per unit for a specific unit of an item.
     *
     * @param Item $item
     * @param Unit $unit
     * @return float|null
     */
    public function costPerUnit(Item $item, Unit $unit): ?float
    {
        $tradingCode = $item->default_unit_code;

        // Get the most recent declared packs for this item
        $packs = $this->packReader->for($item);

        // Get recent purchase lines for this item (limit 50 like SaleRecorder)
        $lines = TransactionLine::query()
            ->whereHas('transaction', fn ($q) => $q->where('type', RecordType::Purchase->value))
            ->where('item_id', $item->id)
            ->with(['transaction', 'item'])
            ->orderByDesc(function ($q) {
                $q->select('transacted_on')->from('transactions')->whereColumn('transactions.id', 'transaction_lines.transaction_id');
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($lines as $tl) {
            $tlPacks = $tl->packs();
            $tlTradingCost = $tl->unit_cost;

            // If the unit matches the trading unit, return the trading unit cost
            if ($unit->code === $tradingCode) {
                if ($tlTradingCost !== null) {
                    return (float) $tlTradingCost;
                }
                continue;
            }

            // If the unit is a declared inner unit with an explicit cost
            $innerCost = $tlPacks->costPerUnit($unit);
            if ($innerCost !== null) {
                return (float) $innerCost;
            }

            // If we have a trading cost and can calculate from pieces per inner unit
            if ($tlTradingCost !== null) {
                $perInner = $tlPacks->per($unit);
                if ($perInner !== null && $perInner > 0) {
                    return round((float) $tlTradingCost / $perInner, 4);
                }
            }
        }

        // Fallback: convert average cost to this unit
        $position = $this->ledger->position($item);
        if ($position->bought > 0) {
            $avgCostPerBaseUnit = $position->averageCost();
            return $avgCostPerBaseUnit * $unit->base_multiplier;
        }

        return null;
    }

    /**
     * Get cost per unit for all units in an item's family, including declared inner units.
     *
     * @param Item $item
     * @return array<string, float|null> unit code => cost per unit
     */
    public function costPerUnitByUnit(Item $item): array
    {
        $catalogueUnits = $this->units->forFamily($item->family);
        $packs = $this->packReader->for($item);

        $costs = [];

        // Calculate for catalogue units
        foreach ($catalogueUnits as $unit) {
            $cost = $this->costPerUnit($item, $unit);
            $costs[$unit->code] = $cost !== null ? round($cost, 2) : null;
        }

        // Also calculate for declared inner units (in case they're not in the catalogue)
        foreach ($packs->toArray() as $pack) {
            $unitCode = $pack['unit'];
            if (!isset($costs[$unitCode])) {
                $unit = $this->units->find($unitCode);
                if ($unit) {
                    $cost = $this->costPerUnit($item, $unit);
                    $costs[$unitCode] = $cost !== null ? round($cost, 2) : null;
                }
            }
        }

        return $costs;
    }

    /**
     * Get the selling price per unit for a specific unit of an item.
     *
     * Retrieves from purchase history - the selling price declared on buy page.
     *
     * @param Item $item
     * @param Unit $unit
     * @return float|null
     */
    public function sellingPricePerUnit(Item $item, Unit $unit): ?float
    {
        $tradingCode = $item->default_unit_code;

        // Get the most recent declared packs for this item
        $packs = $this->packReader->for($item);

        // Get recent purchase lines for this item (limit 50 like SaleRecorder)
        $lines = TransactionLine::query()
            ->whereHas('transaction', fn ($q) => $q->where('type', RecordType::Purchase->value))
            ->where('item_id', $item->id)
            ->with(['transaction', 'item'])
            ->orderByDesc(function ($q) {
                $q->select('transacted_on')->from('transactions')->whereColumn('transactions.id', 'transaction_lines.transaction_id');
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($lines as $tl) {
            $tlPacks = $tl->packs();
            $tlTradingSellingPrice = $tl->selling_price;

            // If the unit matches the trading unit, return the trading unit selling price
            if ($unit->code === $tradingCode) {
                if ($tlTradingSellingPrice !== null) {
                    return (float) $tlTradingSellingPrice;
                }
                continue;
            }

            // If the unit is a declared inner unit with an explicit selling price
            $innerSellingPrice = $tlPacks->sellingPricePerUnit($unit);
            if ($innerSellingPrice !== null) {
                return (float) $innerSellingPrice;
            }

            // If we have a trading selling price and can calculate from pieces per inner unit
            if ($tlTradingSellingPrice !== null) {
                $perInner = $tlPacks->per($unit);
                if ($perInner !== null && $perInner > 0) {
                    return round((float) $tlTradingSellingPrice / $perInner, 4);
                }
            }
        }

        return null;
    }

    /**
     * Get selling price per unit for all units in an item's family, including declared inner units.
     *
     * @param Item $item
     * @return array<string, float|null> unit code => selling price per unit
     */
    public function sellingPricePerUnitByUnit(Item $item): array
    {
        $catalogueUnits = $this->units->forFamily($item->family);
        $packs = $this->packReader->for($item);

        $prices = [];

        // Calculate for catalogue units
        foreach ($catalogueUnits as $unit) {
            $price = $this->sellingPricePerUnit($item, $unit);
            $prices[$unit->code] = $price !== null ? round($price, 2) : null;
        }

        // Also calculate for declared inner units (in case they're not in the catalogue)
        foreach ($packs->toArray() as $pack) {
            $unitCode = $pack['unit'];
            if (!isset($prices[$unitCode])) {
                $unit = $this->units->find($unitCode);
                if ($unit) {
                    $price = $this->sellingPricePerUnit($item, $unit);
                    $prices[$unitCode] = $price !== null ? round($price, 2) : null;
                }
            }
        }

        return $prices;
    }
}