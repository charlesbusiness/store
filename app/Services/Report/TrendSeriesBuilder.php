<?php

namespace App\Services\Report;

use App\Enums\PeriodMode;
use App\Enums\RecordType;
use App\Models\TransactionLine;
use App\Support\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns windowed ledger lines into the bars on the trend chart: sales against
 * purchases for each bucket in the period.
 */
class TrendSeriesBuilder extends Service
{
    /**
     * @param  array<int, array{key: string, label: string, start: CarbonImmutable}>  $buckets
     * @param  Collection<int, TransactionLine>  $lines
     * @return array<int, array{key: string, label: string, start: CarbonImmutable, sales: float, purchases: float}>
     */
    public function build(array $buckets, Collection $lines, PeriodMode $mode): array
    {
        $totals = $this->totalsByBucket($mode, $buckets, $lines);

        return array_map(function (array $bucket) use ($totals) {
            // A bucket may hold only a sale or only a purchase, so each side is
            // defaulted separately rather than defaulting the whole bucket.
            $bucketTotals = $totals[$bucket['key']] ?? [];

            return $bucket + [
                'sales' => (float) ($bucketTotals[RecordType::Sale->value] ?? 0.0),
                'purchases' => (float) ($bucketTotals[RecordType::Purchase->value] ?? 0.0),
            ];
        }, $buckets);
    }

    /**
     * The tallest bar on the chart. Floored at 1 so an empty ledger still
     * divides cleanly.
     *
     * @param  array<int, array{sales: float, purchases: float}>  $trend
     */
    public function peak(array $trend): float
    {
        $values = [];

        foreach ($trend as $bucket) {
            $values[] = $bucket['sales'];
            $values[] = $bucket['purchases'];
        }

        return max(1.0, ...$values);
    }

    /**
     * @param  array<int, array{key: string}>  $buckets
     * @return array<string, array<string, float>>
     */
    private function totalsByBucket(PeriodMode $mode, array $buckets, Collection $lines): array
    {
        $keys = array_column($buckets, 'key');
        $totals = [];

        foreach ($lines as $line) {
            $key = $mode->bucketKey($line->transaction->transacted_on->toImmutable());

            if (! in_array($key, $keys, true)) {
                continue;
            }

            $type = $line->transaction->type->value;
            $totals[$key][$type] = ($totals[$key][$type] ?? 0) + (float) $line->total;
        }

        return $totals;
    }
}
