<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * The reporting grain. Each mode buckets transactions differently and shows a
 * different number of buckets on the trend chart, mirroring the original's
 * daily / weekly / monthly switch.
 */
enum PeriodMode: string
{
    case Daily = 'd';
    case Weekly = 'w';
    case Monthly = 'm';

    public static function default(): self
    {
        return self::Daily;
    }

    /** How many buckets the trend chart shows. */
    public function bucketCount(): int
    {
        return match ($this) {
            self::Daily => 7,
            self::Weekly => 8,
            self::Monthly => 6,
        };
    }

    /**
     * The date that opens this mode's bucket, used to label a bucket key.
     * Weekly snaps back to the Monday of that week.
     */
    public function bucketStart(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $date->startOfDay(),
            self::Weekly => $date->subDays(($date->dayOfWeek + 6) % 7)->startOfDay(),
            self::Monthly => $date->startOfMonth(),
        };
    }

    /**
     * Short axis label for a bucket key. Weeks are labelled by their Monday.
     */
    public function bucketLabel(string $key): string
    {
        return match ($this) {
            self::Daily, self::Weekly => substr($key, 5),
            self::Monthly => CarbonImmutable::createFromFormat('Y-m', $key)->format('M'),
        };
    }

    /**
     * Human label for the period the anchor sits in, e.g. "Week of Oct 05".
     */
    public function periodLabel(CarbonImmutable $anchor): string
    {
        $start = $this->bucketStart($anchor);

        return match ($this) {
            self::Daily => $start->format('D M j Y'),
            self::Weekly => 'Week of '.$start->format('M j'),
            self::Monthly => $start->format('F Y'),
        };
    }

    /**
     * The first transaction date that can fall in the visible window. Widening
     * to a whole month keeps the start-of-month buckets whole.
     *
     * @return array{0: CarbonImmutable, 1: string} the start date and its bucket key
     */
    public function windowStart(CarbonImmutable $anchor): array
    {
        $first = match ($this) {
            self::Daily => $anchor->subDays($this->bucketCount() - 1),
            self::Weekly => $anchor->subDays(7 * ($this->bucketCount() - 1)),
            self::Monthly => $anchor->subMonths($this->bucketCount() - 1),
        };

        $start = $this->bucketStart($first);

        return [$start, $this->bucketKey($start)];
    }

    /**
     * The first and last day covered by the bucket the anchor falls in.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bucketRange(CarbonImmutable $anchor): array
    {
        $start = $this->bucketStart($anchor);

        $end = match ($this) {
            self::Daily => $start,
            self::Weekly => $start->addDays(6),
            self::Monthly => $start->endOfMonth()->startOfDay(),
        };

        return [$start, $end];
    }

    /**
     * The bucket key a transaction date collapses into for this mode.
     */
    public function bucketKey(CarbonImmutable $date): string
    {
        return match ($this) {
            self::Daily => $date->format('Y-m-d'),
            self::Monthly => $date->format('Y-m'),
            self::Weekly => $this->bucketStart($date)->format('Y-m-d'),
        };
    }
}
