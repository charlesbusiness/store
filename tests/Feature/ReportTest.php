<?php

namespace Tests\Feature;

use App\Enums\PeriodMode;
use App\Services\Report\BucketResolver;
use App\Services\Report\ReportService;
use App\Services\Report\ReportSummary;
use App\Support\ValueFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The report maths: bucket boundaries, period totals and the trend series.
 *
 * Week buckets start on Monday, the trend shows the last 7 days, 8 weeks or
 * 6 months depending on the grain, and stock value is all-time rather than
 * limited to the period.
 */
class ReportTest extends TestCase
{
    private const ANCHOR = '2026-10-07'; // a Wednesday

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUnits();
        Carbon::setTestNow(self::ANCHOR.' 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function anchor(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', self::ANCHOR)->startOfDay();
    }

    private function report(PeriodMode $mode, ?string $anchor = null): ReportSummary
    {
        $date = $anchor
            ? CarbonImmutable::createFromFormat('Y-m-d', $anchor)->startOfDay()
            : $this->anchor();

        return app(ReportService::class)->summary($mode, $date);
    }

    public function test_a_bucket_covers_only_its_own_period(): void
    {
        $item = $this->item('Rice', 'count', 'bag');

        // One line on each side of a Sunday/Monday boundary: the Sunday closes the
        // week of Monday 28 September, the Monday opens the week being reported.
        $this->recordLine($item, 'sale', 1, 'bag', 5000, '2026-10-04');
        $this->recordLine($item, 'sale', 1, 'bag', 7000, '2026-10-05');

        $weekly = app(BucketResolver::class)->current(PeriodMode::Weekly, $this->anchor());

        $this->assertSame('2026-10-05', $weekly['start']->toDateString());
        $this->assertSame(7000.0, $this->report(PeriodMode::Weekly)->sales);

        // A month is the calendar month, first to last day.
        $this->recordLine($item, 'sale', 1, 'bag', 5000, '2026-10-01');
        $this->recordLine($item, 'sale', 1, 'bag', 3000, '2026-10-31');

        $monthly = app(BucketResolver::class)->current(PeriodMode::Monthly, $this->anchor());

        $this->assertSame('2026-10-01', $monthly['start']->toDateString());
        $this->assertSame('2026-10-31', $monthly['start']->endOfMonth()->toDateString());
        // All four October sales, including the one the week before dropped.
        $this->assertSame(20000.0, $this->report(PeriodMode::Monthly)->sales);
    }

    public function test_a_period_totals_only_its_own_lines_while_the_chart_looks_back(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 10, 'bag', 25000, self::ANCHOR);
        $this->recordLine($item, 'sale', 2, 'bag', 9000, self::ANCHOR);
        $this->recordLine($item, 'sale', 1, 'bag', 9000, '2026-10-06');

        $daily = $this->report(PeriodMode::Daily);

        // 2 bags at 9,000 is an 18,000 sale; the 9,000 from the 6th is outside.
        $this->assertSame(18000.0, $daily->sales);
        $this->assertSame(25000.0, $daily->purchases);
        $this->assertSame(-7000.0, $daily->difference());
        $this->assertTrue($daily->isDeficit());

        // An anchor in the past reports that period instead of today.
        $this->recordLine($item, 'sale', 1, 'bag', 4000, '2026-03-15');

        $this->assertSame(4000.0, $this->report(PeriodMode::Daily, '2026-03-15')->sales);

        // The headline is October alone -- 18,000 on the 7th plus the 9,000 from
        // the 6th that a daily bucket excluded. The chart reaches back six months,
        // to May, so it does not reach the March line at all.
        $monthly = $this->report(PeriodMode::Monthly);
        $chart = collect($monthly->trend)->keyBy(fn (array $b) => $b['start']->format('Y-m'));

        $this->assertSame(27000.0, $monthly->sales);
        $this->assertSame(27000.0, (float) $chart['2026-10']['sales']);
        $this->assertSame(0.0, (float) $chart['2026-05']['sales']);
        $this->assertArrayNotHasKey('2026-03', $chart->all());
    }

    public static function grains(): array
    {
        // Buckets shown, and the first one, per grain.
        return [
            'daily' => [PeriodMode::Daily, 7, '2026-10-01', '10-01'],
            'weekly' => [PeriodMode::Weekly, 8, '2026-08-17', null],
            'monthly' => [PeriodMode::Monthly, 6, '2026-05-01', 'May'],
        ];
    }

    #[DataProvider('grains')]
    public function test_the_trend_shows_the_expected_buckets(PeriodMode $mode, int $buckets, string $first, ?string $label): void
    {
        $trend = $this->report($mode)->trend;

        $this->assertCount($buckets, $trend);
        $this->assertSame($first, $trend[0]['start']->toDateString());

        if ($label !== null) {
            $this->assertSame($label, $trend[0]['label']);
        }
    }

    public function test_each_trend_bucket_sums_only_its_own_lines(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 5, 'bag', 12500, '2026-10-05');
        $this->recordLine($item, 'sale', 2, 'bag', 9000, '2026-10-05');
        $this->recordLine($item, 'sale', 1, 'bag', 9000, '2026-10-06');

        $byDay = collect($this->report(PeriodMode::Daily)->trend)
            ->keyBy(fn (array $bucket) => $bucket['start']->toDateString());

        $this->assertSame(12500.0, (float) $byDay['2026-10-05']['purchases']);
        // 2 bags at 9,000.
        $this->assertSame(18000.0, (float) $byDay['2026-10-05']['sales']);
        $this->assertSame(9000.0, (float) $byDay['2026-10-06']['sales']);
        $this->assertSame(0.0, (float) $byDay['2026-10-04']['sales']);
    }

    public function test_stock_value_is_all_time_not_limited_to_the_period(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'purchase', 10, 'bag', 25000, '2025-01-05');
        $this->recordLine($item, 'sale', 2, 'bag', 9000, '2026-10-07');

        // The whole remaining 8 bags at 2,500 each, bought long before the period.
        $this->assertSame(20000.0, $this->report(PeriodMode::Daily)->stockValue);
    }

    public function test_the_items_table_rolls_up_the_period_and_ranks_by_value(): void
    {
        $rice = $this->item('Rice', 'count', 'bag');
        $sugar = $this->item('Sugar', 'weight', 'kg');

        $this->recordLine($rice, 'purchase', 10, 'bag', 25000, self::ANCHOR);
        $this->recordLine($rice, 'sale', 2, 'bag', 9000, self::ANCHOR);
        $this->recordLine($sugar, 'purchase', 20, 'kg', 24000, self::ANCHOR);
        $this->recordLine($sugar, 'sale', 5, 'kg', 7000, self::ANCHOR);
        // Older than the daily period, so not in the item table.
        $this->recordLine($rice, 'sale', 1, 'bag', 9000, '2026-10-01');

        $summary = $this->report(PeriodMode::Daily);

        $sold = collect($summary->itemsSold);
        // Ranked by value: Sugar's 35,000 (5 kg at 7,000) beats Rice's 18,000.
        $this->assertSame(['Sugar', 'Rice'], $sold->pluck('item.name')->values()->all());
        // 2 bags at 9,000.
        $this->assertSame(18000.0, (float) $sold->firstWhere('item.name', 'Rice')['total']);
        $this->assertSame(2.0, (float) $sold->firstWhere('item.name', 'Rice')['display_qty']);

        $bought = collect($summary->itemsBought);
        $this->assertSame(25000.0, (float) $bought->firstWhere('item.name', 'Rice')['total']);
        // 20 kg restated in the item's own trading unit.
        $this->assertSame(20.0, (float) $bought->firstWhere('item.name', 'Sugar')['display_qty']);
    }

    public function test_an_empty_ledger_reports_nothing(): void
    {
        $summary = $this->report(PeriodMode::Daily);

        $this->assertSame(0.0, $summary->sales);
        $this->assertSame(0.0, $summary->purchases);
        $this->assertSame(0.0, $summary->stockValue);
        $this->assertFalse($summary->hasActivity());
        $this->assertCount(7, $summary->trend);
        $this->assertSame([], $summary->itemsSold);
    }

    // ------------------------------------------------------------------- the page

    public function test_the_report_page_renders_every_grain_and_recovers_from_bad_input(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'sale', 1, 'bag', 1234.5, self::ANCHOR);

        $this->actingAs($this->user());

        foreach (['d', 'w', 'm'] as $mode) {
            $this->get(route('reports.index', ['mode' => $mode, 'anchor' => self::ANCHOR]))
                ->assertOk()
                ->assertSee(ValueFormatter::money(1234.5), false);
        }

        $this->get(route('reports.index'))->assertOk();

        // An unknown grain falls back to daily, and an unreadable anchor to today.
        // Neither is worth an error: the page is a link target.
        foreach ([['fortnightly', '2026-10-07'], ['d', 'not-a-date']] as [$mode, $anchor]) {
            $this->get(route('reports.index', ['mode' => $mode, 'anchor' => $anchor]))
                ->assertOk()
                ->assertSee(PeriodMode::Daily->label(), false);
        }
    }
}
