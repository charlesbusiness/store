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
 *
 * Supports optional custom date range [start, end] for flexible reporting.
 */
class ReportService extends Service
{
    public function __construct(
        private readonly BucketResolver $buckets,
        private readonly TrendSeriesBuilder $trend,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * @param  ?array{0: CarbonImmutable, 1: CarbonImmutable}  $dateRange  [start, end]
     */
    public function summary(PeriodMode $mode, CarbonImmutable $anchor, ?array $dateRange = null): ReportSummary
    {
        $current = $dateRange
            ? $this->bucketForEnd($mode, $anchor)
            : $this->buckets->current($mode, $anchor);

        $lines = $this->windowLines($mode, $anchor, $dateRange);

        $inPeriod = $dateRange
            ? $lines // All lines in the custom range
            : $lines->filter(
                fn (TransactionLine $line) => $mode->bucketKey($line->transaction->transacted_on->toImmutable()) === $current['key']
            );

        $seriesBuckets = $dateRange
            ? $this->buckets->bucketsForRange($mode, $dateRange[0], $dateRange[1])
            : $this->buckets->buckets($mode, $anchor);

        $series = $this->trend->build($seriesBuckets, $lines, $mode);

        return new ReportSummary(
            mode: $mode,
            anchor: $anchor,
            periodLabel: $dateRange
                ? $this->periodLabelForRange($mode, $dateRange[0], $dateRange[1])
                : $current['label'],
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
     * @param  ?array{0: CarbonImmutable, 1: CarbonImmutable}  $dateRange  [start, end]
     * @return Collection<int, TransactionLine>
     */
    private function windowLines(PeriodMode $mode, CarbonImmutable $anchor, ?array $dateRange = null): Collection
    {
        if ($dateRange) {
            $start = $dateRange[0];
            $end = $dateRange[1];
        } else {
            [$start] = $mode->windowStart($anchor);
            [, $end] = $mode->bucketRange($anchor);
        }

        return TransactionLine::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->whereDate('transactions.transacted_on', '>=', $start->toDateString())
            ->whereDate('transactions.transacted_on', '<=', $end->toDateString())
            ->select('transaction_lines.*')
            ->with(['item.displayUnit', 'unit', 'transaction'])
            ->orderBy('transactions.transacted_on')
            ->get();
    }

    private function bucketForEnd(PeriodMode $mode, CarbonImmutable $anchor): array
    {
        $start = $mode->bucketStart($anchor);
        return [
            'key' => $mode->bucketKey($start),
            'label' => $mode->periodLabel($anchor),
            'start' => $start,
        ];
    }

    private function periodLabelForRange(PeriodMode $mode, CarbonImmutable $start, CarbonImmutable $end): string
    {
        return match ($mode) {
            PeriodMode::Daily => $start->format('D M j Y') . ' – ' . $end->format('D M j Y'),
            PeriodMode::Weekly => 'Week of ' . $start->format('M j') . ' – ' . $end->format('M j'),
            PeriodMode::Monthly => $start->format('F Y') . ' – ' . $end->format('F Y'),
        };
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
