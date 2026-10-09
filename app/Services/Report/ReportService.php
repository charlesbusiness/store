<?php

namespace App\Services\Report;

use App\Enums\PeriodMode;
use App\Enums\RecordType;
use App\Models\Item;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Services\Stock\StockLedger;
use App\Support\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The entry point for the reports page: one call, one snapshot.
 *
 * Sales, purchases and the per-item tables cover only the bucket the anchor
 * date falls in, while the stock valuation covers everything ever bought. That
 * split is the original ledger's behaviour and is deliberate: the figures
 * answer "how did this period go" and "what is on the shelf right now".
 */
class ReportService extends Service
{
    public function __construct(
        private readonly BucketResolver $buckets,
        private readonly TrendSeriesBuilder $trend,
        private readonly StockLedger $ledger,
    ) {}

    public function summary(PeriodMode $mode, CarbonImmutable $anchor): ReportSummary
    {
        $current = $this->buckets->current($mode, $anchor);
        $lines = $this->windowLines($mode, $anchor);

        $inPeriod = $lines->filter(
            fn (TransactionLine $line) => $mode->bucketKey($line->transaction->transacted_on->toImmutable()) === $current['key']
        );

        $series = $this->trend->build($this->buckets->buckets($mode, $anchor), $lines, $mode);

        return new ReportSummary(
            mode: $mode,
            anchor: $anchor,
            periodLabel: $current['label'],
            sales: $this->totalOf($inPeriod, RecordType::Sale),
            purchases: $this->totalOf($inPeriod, RecordType::Purchase),
            stockValue: $this->ledger->totalStockValue(),
            trend: $series,
            trendMax: $this->trend->peak($series),
            itemsSold: $this->itemTotals($inPeriod, RecordType::Sale),
            itemsBought: $this->itemTotals($inPeriod, RecordType::Purchase),
        );
    }

    /**
     * Every line inside the visible window, so the chart and the period figures
     * come from a single query.
     *
     * @return Collection<int, TransactionLine>
     */
    private function windowLines(PeriodMode $mode, CarbonImmutable $anchor): Collection
    {
        [$start] = $mode->windowStart($anchor);
        [, $end] = $mode->bucketRange($anchor);

        return TransactionLine::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            // whereDate rather than a string range, so the comparison does not
            // depend on how a given driver stores a date-only column.
            ->whereDate('transactions.transacted_on', '>=', $start->toDateString())
            ->whereDate('transactions.transacted_on', '<=', $end->toDateString())
            // Both tables are joined, so the columns have to be named rather
            // than left as *, or id and timestamps collide.
            ->select('transaction_lines.*')
            ->with(['item.displayUnit', 'unit', 'transaction'])
            ->orderBy('transactions.transacted_on')
            ->get();
    }

    private function totalOf(Collection $lines, RecordType $type): float
    {
        return round(
            $lines->filter(fn (TransactionLine $line) => $line->transaction->type === $type)->sum('total'),
            2
        );
    }

    /**
     * Per-item totals for one side of the ledger, biggest value first.
     *
     * Quantities are summed in base units then restated in each item's own
     * trading unit, so "3.5 bags" reads the same way it did before.
     *
     * @return array<int, array{item: Item, unit: Unit|null, base_qty: float, display_qty: float, total: float}>
     */
    private function itemTotals(Collection $lines, RecordType $type): array
    {
        $grouped = [];

        foreach ($lines->filter(fn (TransactionLine $line) => $line->transaction->type === $type) as $line) {
            $grouped[$line->item_id] ??= ['item' => $line->item, 'base_qty' => 0.0, 'total' => 0.0];
            $grouped[$line->item_id]['base_qty'] += (float) $line->base_qty;
            $grouped[$line->item_id]['total'] += (float) $line->total;
        }

        $rows = [];

        foreach ($grouped as $row) {
            $unit = $row['item']?->displayUnit;
            $row['unit'] = $unit;
            $row['total'] = round($row['total'], 2);
            $row['display_qty'] = $unit ? $row['base_qty'] / $unit->base_multiplier : $row['base_qty'];
            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $rows;
    }
}
