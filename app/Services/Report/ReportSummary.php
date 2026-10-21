<?php

namespace App\Services\Report;

use App\Enums\PeriodMode;
use App\Models\Item;
use App\Models\Unit;
use App\Support\ValueFormatter;
use Carbon\CarbonImmutable;

/**
 * Everything the reports page needs, computed once.
 *
 * A read-only snapshot: the view formats and renders it without asking any
 * service a further question.
 */
final readonly class ReportSummary
{
    /**
     * @param  array<int, array{key: string, label: string, start: CarbonImmutable, sales: float, purchases: float}>  $trend
     * @param  array<int, array{item: Item, unit: Unit, base_qty: float, display_qty: float, total: float, cost: ?float, profit: ?float}>  $itemsSold
     * @param  array<int, array{item: Item, unit: Unit, base_qty: float, display_qty: float, total: float, cost: ?float, profit: ?float}>  $itemsBought
     * @param  float  $trendMax  the largest bar on the chart, never below 1
     */
    public function __construct(
        public PeriodMode $mode,
        public CarbonImmutable $anchor,
        public string $periodLabel,
        public float $sales,
        public float $purchases,
        public float $stockValue,
        public array $trend,
        public float $trendMax,
        public array $itemsSold,
        public array $itemsBought,
        public ProfitSummary $profit,
    ) {}

    /** Sales minus purchases, negative when the period spent more than it earned. */
    public function difference(): float
    {
        return round($this->sales - $this->purchases, 2);
    }

    public function isDeficit(): bool
    {
        return $this->difference() < 0;
    }

    public function hasActivity(): bool
    {
        return $this->sales > 0 || $this->purchases > 0;
    }

    public function trendBars(): array
    {
        return array_map(fn (array $bucket) => $bucket + [
            'sales_height' => ValueFormatter::barHeight($bucket['sales'], $this->trendMax),
            'purchases_height' => ValueFormatter::barHeight($bucket['purchases'], $this->trendMax),
        ], $this->trend);
    }
}
