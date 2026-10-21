<?php

namespace App\Services\Report;

use App\Enums\RecordType;
use App\Models\TransactionLine;
use App\Support\Service;
use Illuminate\Support\Collection;

/**
 * Works out what the goods sold in a period made.
 *
 * Only sales count. A purchase is stock on the shelf, not money made, so it
 * never enters the profit even though it carries the same kind of line.
 *
 * The cost comes off each sale line's own frozen snapshot rather than a live
 * average: what a sale made is a fact about the moment it happened, and it
 * must not drift when the item is bought again at a different price.
 */
class ProfitCalculator extends Service
{
    /**
     * @param  Collection<int, TransactionLine>  $lines  the lines in the period
     */
    public function forLines(Collection $lines): ProfitSummary
    {
        $sales = $lines->filter(fn (TransactionLine $line) => $line->transaction->type === RecordType::Sale);

        $revenue = 0.0;
        $knownRevenue = 0.0;
        $costOfGoods = 0.0;
        $unknownLines = 0;

        foreach ($sales as $line) {
            $revenue += (float) $line->total;

            if ($line->cost === null) {
                $unknownLines++;

                continue;
            }

            $knownRevenue += (float) $line->total;
            $costOfGoods += (float) $line->cost;
        }

        $complete = $unknownLines === 0;
        $profit = $complete ? round($revenue - $costOfGoods, 2) : null;

        return new ProfitSummary(
            revenue: round($revenue, 2),
            costOfGoods: round($costOfGoods, 2),
            profit: $profit,
            knownProfit: round($knownRevenue - $costOfGoods, 2),
            unknownLines: $unknownLines,
            margin: $profit !== null && $revenue > 0 ? round($profit / $revenue, 4) : null,
        );
    }
}
