<?php

namespace App\Services\Report;

use App\Enums\PeriodMode;
use App\Enums\RecordType;
use App\Models\Branch;
use App\Models\Item;
use App\Models\TransactionLine;
use App\Models\Unit;
use App\Services\Stock\StockLedger;
use App\Support\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\TenantContext;

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
        private readonly ProfitCalculator $profit,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  ?array{0: CarbonImmutable, 1: CarbonImmutable}  $dateRange  [start, end]
     */
    public function summary(
        PeriodMode $mode,
        CarbonImmutable $anchor,
        ?array $dateRange = null,
        string $scope = 'account',
        ?int $branchId = null,
    ): ReportSummary {
        $current = $dateRange
            ? $this->bucketForEnd($mode, $anchor)
            : $this->buckets->current($mode, $anchor);

        $lines = $this->windowLines($mode, $anchor, $dateRange, $scope, $branchId);
        $inPeriod = $this->periodLines($mode, $anchor, $dateRange, $lines);
        $purchaseTotal = $this->purchaseTotal($inPeriod, $scope, $branchId, $mode, $anchor, $dateRange);

        $seriesBuckets = $dateRange
            ? $this->buckets->bucketsForRange($mode, $dateRange[0], $dateRange[1])
            : $this->buckets->buckets($mode, $anchor);

        $series = $this->trend->build($seriesBuckets, $lines, $mode);

        $stockValue = $scope === 'branch'
            ? $this->ledger->totalStockValueForBranch((int) $branchId)
            : $this->ledger->totalStockValueForAccount();

        return new ReportSummary(
            mode: $mode,
            anchor: $anchor,
            periodLabel: $dateRange
                ? $this->periodLabelForRange($mode, $dateRange[0], $dateRange[1])
                : $current['label'],
            sales: $this->totalOf($inPeriod, RecordType::Sale),
            purchases: $purchaseTotal,
            stockValue: $stockValue,
            trend: $series,
            trendMax: $this->trend->peak($series),
            itemsSold: $this->itemTotals($inPeriod, RecordType::Sale),
            itemsBought: $this->itemTotals($inPeriod, RecordType::Purchase),
            profit: $this->profit->forLines($inPeriod),
            scope: $scope,
        );
    }

    /**
     * The same headline figures for every branch the user can see, so an
     * account-wide report can show where the money came from.
     *
     * @param  Collection<int, Branch>  $branches
     * @return array<int, array{branch_id: int, name: string, sales: float, purchases: float, profit: ?float, stock_value: float, is_selected: bool}>
     */
    public function branchBreakdown(
        PeriodMode $mode,
        CarbonImmutable $anchor,
        ?array $dateRange,
        Collection $branches,
        ?int $selectedBranchId = null,
    ): array {
        $rows = [];

        foreach ($branches as $branch) {
            $lines = $this->windowLines($mode, $anchor, $dateRange, 'branch', $branch->id);
            $inPeriod = $this->periodLines($mode, $anchor, $dateRange, $lines);
            $profit = $this->profit->forLines($inPeriod);

            $rows[] = [
                'branch_id' => $branch->id,
                'name' => $branch->name,
                'sales' => $this->totalOf($inPeriod, RecordType::Sale),
                'purchases' => round(
                    $this->totalOf($inPeriod, RecordType::Purchase)
                        + $this->transferPurchaseAdjustment($mode, $anchor, $dateRange, $branch->id),
                    2,
                ),
                'profit' => $profit->profit ?? $profit->knownProfit,
                'stock_value' => $this->ledger->totalStockValueForBranch($branch->id),
                'is_selected' => $branch->id === $selectedBranchId,
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, TransactionLine>  $lines */
    private function periodLines(PeriodMode $mode, CarbonImmutable $anchor, ?array $dateRange, Collection $lines): Collection
    {
        if ($dateRange) {
            return $lines;
        }

        $current = $this->buckets->current($mode, $anchor);

        return $lines->filter(
            fn (TransactionLine $line) => $mode->bucketKey($line->transaction->transacted_on->toImmutable()) === $current['key']
        );
    }

    /**
     * Every line inside the visible window, so the chart and the period figures
     * come from a single query.
     *
     * @param  ?array{0: CarbonImmutable, 1: CarbonImmutable}  $dateRange  [start, end]
     * @return Collection<int, TransactionLine>
     */
    private function windowLines(
        PeriodMode $mode,
        CarbonImmutable $anchor,
        ?array $dateRange = null,
        string $scope = 'account',
        ?int $branchId = null,
    ): Collection {
        if ($dateRange) {
            $start = $dateRange[0];
            $end = $dateRange[1];
        } else {
            [$start] = $mode->windowStart($anchor);
            [, $end] = $mode->bucketRange($anchor);
        }

        return TransactionLine::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.tenant_id', $this->context->tenantId())
            ->when($scope !== 'branch', fn ($query) => $query->whereNull('transactions.stock_transfer_id'))
            ->when($scope === 'branch', fn ($query) => $query->where('transactions.branch_id', $branchId))
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

    private function purchaseTotal(
        Collection $lines,
        string $scope,
        ?int $branchId,
        PeriodMode $mode,
        CarbonImmutable $anchor,
        ?array $dateRange,
    ): float {
        $total = $this->totalOf($lines, RecordType::Purchase);

        if ($scope !== 'branch' || $branchId === null) {
            return $total;
        }

        return round($total + $this->transferPurchaseAdjustment($mode, $anchor, $dateRange, $branchId), 2);
    }

    private function transferPurchaseAdjustment(
        PeriodMode $mode,
        CarbonImmutable $anchor,
        ?array $dateRange,
        int $branchId,
    ): float {
        [$start, $end] = $dateRange ?? $mode->bucketRange($anchor);

        $incoming = DB::table('stock_transfer_lines')
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_lines.stock_transfer_id')
            ->where('stock_transfers.tenant_id', $this->context->tenantId())
            ->where('stock_transfers.status', 'completed')
            ->where('stock_transfers.destination_branch_id', $branchId)
            ->whereDate('stock_transfers.transferred_on', '>=', $start->toDateString())
            ->whereDate('stock_transfers.transferred_on', '<=', $end->toDateString())
            ->sum('stock_transfer_lines.total_cost');

        $outgoing = DB::table('stock_transfer_lines')
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_lines.stock_transfer_id')
            ->where('stock_transfers.tenant_id', $this->context->tenantId())
            ->where('stock_transfers.status', 'completed')
            ->where('stock_transfers.source_branch_id', $branchId)
            ->whereDate('stock_transfers.transferred_on', '>=', $start->toDateString())
            ->whereDate('stock_transfers.transferred_on', '<=', $end->toDateString())
            ->sum('stock_transfer_lines.total_cost');

        return (float) $incoming - (float) $outgoing;
    }

    /**
     * Per-item totals for one side of the ledger, biggest value first.
     *
     * Quantities are summed in base units then restated in each item's own
     * trading unit, so "3.5 bags" reads the same way it did before.
     *
     * A sale also carries the cost it was sold at, and what it made: the same
     * frozen snapshot the period profit is built from, so an item's row and the
     * headline always tell the same story. A purchase is not a profit and
     * carries neither figure.
     *
     * @return array<int, array{item: Item, unit: Unit|null, base_qty: float, display_qty: float, total: float, cost: ?float, profit: ?float}>
     */
    private function itemTotals(Collection $lines, RecordType $type): array
    {
        $grouped = [];

        foreach ($lines->filter(fn (TransactionLine $line) => $line->transaction->type === $type) as $line) {
            $grouped[$line->item_id] ??= [
                'item' => $line->item,
                'base_qty' => 0.0,
                'total' => 0.0,
                'cost' => 0.0,
                'unknown_cost' => 0,
            ];
            $grouped[$line->item_id]['base_qty'] += (float) $line->base_qty;
            $grouped[$line->item_id]['total'] += (float) $line->total;

            if ($type !== RecordType::Sale) {
                continue;
            }

            if ($line->cost === null) {
                $grouped[$line->item_id]['unknown_cost']++;

                continue;
            }

            $grouped[$line->item_id]['cost'] += (float) $line->cost;
        }

        $rows = [];

        foreach ($grouped as $row) {
            $unit = $row['item']?->displayUnit;
            $row['unit'] = $unit;
            $row['total'] = round($row['total'], 2);
            $row['display_qty'] = $unit ? $row['base_qty'] / $unit->base_multiplier : $row['base_qty'];

            // A sale line whose cost was never recorded has an unknown profit,
            // and the unknown travels up to the item, the same way it travels
            // up to the period.
            $known = $type === RecordType::Sale && $row['unknown_cost'] === 0;
            $row['cost'] = $known ? round($row['cost'], 2) : null;
            $row['profit'] = $known ? round($row['total'] - $row['cost'], 2) : null;

            unset($row['unknown_cost']);

            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    public function trend(PeriodMode $mode, CarbonImmutable $anchor, string $scope = 'account', ?int $branchId = null): array
    {
        $buckets = $this->buckets->buckets($mode, $anchor);
        $lines = $this->windowLines($mode, $anchor, null, $scope, $branchId);
        return $this->trend->build($buckets, $lines, $mode);
    }
}
