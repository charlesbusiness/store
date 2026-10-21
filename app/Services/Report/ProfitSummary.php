<?php

namespace App\Services\Report;

/**
 * What the goods sold in a period made.
 *
 * Revenue is what the period took, cost of goods is what those goods cost at
 * the moment they were sold, and the difference is the profit.
 *
 * A sale line with no recorded cost has an unknown profit, and the unknown
 * travels all the way up: profit is null whenever any line in the period is
 * missing its cost. A partial total that looked complete would be worse than
 * no figure at all, so the known part is reported alongside it rather than
 * quietly folded into the headline.
 */
final readonly class ProfitSummary
{
    /**
     * @param  float  $revenue  what the period's sales took
     * @param  float  $costOfGoods  what those goods cost, over the lines that recorded a cost
     * @param  ?float  $profit  revenue minus cost of goods, or null when a line's cost is unknown
     * @param  float  $knownProfit  the profit over the lines whose cost is known
     * @param  int  $unknownLines  sale lines in the period with no recorded cost
     * @param  ?float  $margin  profit as a ratio of revenue, or null when it cannot be known
     */
    public function __construct(
        public float $revenue,
        public float $costOfGoods,
        public ?float $profit,
        public float $knownProfit,
        public int $unknownLines,
        public ?float $margin,
    ) {}

    /** True when every sale line in the period had a cost to work from. */
    public function isComplete(): bool
    {
        return $this->unknownLines === 0;
    }
}
