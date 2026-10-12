<?php

namespace App\Services\Report;

use App\Enums\PeriodMode;
use App\Support\Service;
use Carbon\CarbonImmutable;

/**
 * Turns dates into the buckets a report is grouped by.
 *
 * Days key on the day itself, months on Y-m, and weeks snap back to their
 * Monday, which is what the original ledger did with
 * `d.setDate(d.getDate() - ((d.getDay() + 6) % 7))`.
 */
class BucketResolver extends Service
{
    public function key(PeriodMode $mode, CarbonImmutable $date): string
    {
        return $mode->bucketKey($date);
    }

    /**
     * The buckets a trend chart should show, oldest first.
     *
     * @return array<int, array{key: string, label: string, start: CarbonImmutable}>
     */
    public function buckets(PeriodMode $mode, CarbonImmutable $anchor): array
    {
        $buckets = [];

        for ($i = $mode->bucketCount() - 1; $i >= 0; $i--) {
            $start = $mode->bucketStart($this->stepBack($mode, $anchor, $i));

            $buckets[] = [
                'key' => $this->key($mode, $start),
                'label' => $mode->bucketLabel($this->key($mode, $start)),
                'start' => $start,
            ];
        }

        return $buckets;
    }

    /**
     * The bucket the anchor falls in.
     *
     * @return array{key: string, label: string, start: CarbonImmutable}
     */
    public function current(PeriodMode $mode, CarbonImmutable $anchor): array
    {
        $start = $mode->bucketStart($anchor);

        return [
            'key' => $this->key($mode, $start),
            'label' => $mode->periodLabel($anchor),
            'start' => $start,
        ];
    }

    /**
     * How far back a bucket index reaches. Weeks step a week at a time and
     * months a month at a time; both then snap to the bucket start.
     */
    private function stepBack(PeriodMode $mode, CarbonImmutable $anchor, int $steps): CarbonImmutable
    {
        return match ($mode) {
            PeriodMode::Daily => $anchor->subDays($steps),
            PeriodMode::Weekly => $anchor->subDays(7 * $steps),
            PeriodMode::Monthly => $anchor->subMonths($steps),
        };
    }

    /**
     * Generate buckets for a custom date range.
     *
     * @return array<int, array{key: string, label: string, start: CarbonImmutable}>
     */
    public function bucketsForRange(PeriodMode $mode, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $buckets = [];
        $current = $mode->bucketStart($start);

        while ($current <= $end) {
            $buckets[] = [
                'key' => $this->key($mode, $current),
                'label' => $mode->bucketLabel($this->key($mode, $current)),
                'start' => $current,
            ];

            $current = match ($mode) {
                PeriodMode::Daily => $current->addDay(),
                PeriodMode::Weekly => $current->addWeek(),
                PeriodMode::Monthly => $current->addMonth(),
            };
        }

        return $buckets;
    }
}
