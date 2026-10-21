<?php

namespace Tests\Feature;

use App\Enums\PeriodMode;
use App\Services\Report\BucketResolver;
use App\Services\Report\ReportService;
use App\Services\Report\ReportSummary;
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

        // Nothing sold, so nothing made and no margin to speak of.
        $this->assertSame(0.0, $summary->profit->profit);
        $this->assertTrue($summary->profit->isComplete());
        $this->assertNull($summary->profit->margin);
    }

    public function test_profit_is_what_the_goods_sold_made(): void
    {
        $item = $this->item('Rice', 'count', 'bag');

        // A purchase is stock on the shelf, not money made, so it never counts
        // towards the profit.
        $this->recordLine($item, 'purchase', 10, 'bag', 25000, self::ANCHOR);
        // 2 bags at 9,000, sold out of stock that had cost 5,000.
        $this->recordLine($item, 'sale', 2, 'bag', 9000, self::ANCHOR, 5000);

        $profit = $this->report(PeriodMode::Daily)->profit;

        $this->assertSame(18000.0, $profit->revenue);
        $this->assertSame(5000.0, $profit->costOfGoods);
        $this->assertSame(13000.0, $profit->profit);
        $this->assertSame(13000.0, $profit->knownProfit);
        $this->assertSame(0, $profit->unknownLines);
        $this->assertTrue($profit->isComplete());
        // 13,000 of 18,000.
        $this->assertSame(0.7222, $profit->margin);
    }

    public function test_profit_is_unknown_when_a_sale_never_recorded_its_cost(): void
    {
        $item = $this->item('Rice', 'count', 'bag');

        // Recorded before costs were snapshotted, so its profit is unknown.
        $this->recordLine($item, 'sale', 1, 'bag', 9000, self::ANCHOR);
        $this->recordLine($item, 'sale', 1, 'bag', 5000, self::ANCHOR, 3000);

        $profit = $this->report(PeriodMode::Daily)->profit;

        // A partial total that looked complete would be worse than no figure.
        $this->assertNull($profit->profit);
        $this->assertNull($profit->margin);
        $this->assertSame(1, $profit->unknownLines);
        $this->assertFalse($profit->isComplete());
        // The part that is known: 5,000 sold for a cost of 3,000.
        $this->assertSame(2000.0, $profit->knownProfit);
        $this->assertSame(3000.0, $profit->costOfGoods);
    }

    public function test_profit_covers_only_the_lines_of_its_own_period(): void
    {
        $item = $this->item('Rice', 'count', 'bag');

        $this->recordLine($item, 'sale', 1, 'bag', 9000, self::ANCHOR, 5000);
        // The day before, so outside the daily bucket.
        $this->recordLine($item, 'sale', 1, 'bag', 4000, '2026-10-06', 2000);

        $profit = $this->report(PeriodMode::Daily)->profit;

        $this->assertSame(9000.0, $profit->revenue);
        $this->assertSame(5000.0, $profit->costOfGoods);
        $this->assertSame(4000.0, $profit->profit);
    }

    public function test_profit_follows_the_selected_date_range(): void
    {
        $item = $this->item('Rice', 'count', 'bag');

        $this->recordLine($item, 'sale', 1, 'bag', 9000, '2026-10-01', 5000);
        $this->recordLine($item, 'sale', 1, 'bag', 7000, '2026-10-05', 4000);
        // Outside the range.
        $this->recordLine($item, 'sale', 1, 'bag', 3000, '2026-10-20', 1000);

        $range = [
            CarbonImmutable::createFromFormat('Y-m-d', '2026-10-01')->startOfDay(),
            CarbonImmutable::createFromFormat('Y-m-d', '2026-10-05')->endOfDay(),
        ];

        $summary = app(ReportService::class)->summary(PeriodMode::Daily, $range[1], $range);

        $this->assertSame(16000.0, $summary->sales);
        $this->assertSame(9000.0, $summary->profit->costOfGoods);
        $this->assertSame(7000.0, $summary->profit->profit);
    }

    public function test_the_items_table_carries_what_each_item_made(): void
    {
        $rice = $this->item('Rice', 'count', 'bag');
        $sugar = $this->item('Sugar', 'weight', 'kg');

        $this->recordLine($rice, 'sale', 2, 'bag', 9000, self::ANCHOR, 5000);
        $this->recordLine($sugar, 'sale', 5, 'kg', 7000, self::ANCHOR);
        $this->recordLine($rice, 'purchase', 10, 'bag', 25000, self::ANCHOR);

        $sold = collect($this->report(PeriodMode::Daily)->itemsSold);

        $rice = $sold->firstWhere('item.name', 'Rice');
        $this->assertSame(18000.0, (float) $rice['total']);
        $this->assertSame(5000.0, (float) $rice['cost']);
        $this->assertSame(13000.0, (float) $rice['profit']);

        // Sugar was sold before costs were snapshotted, so it has no profit.
        $sugar = $sold->firstWhere('item.name', 'Sugar');
        $this->assertNull($sugar['cost']);
        $this->assertNull($sugar['profit']);

        // A purchase is not a profit, so it carries neither figure.
        $bought = collect($this->report(PeriodMode::Daily)->itemsBought)->firstWhere('item.name', 'Rice');
        $this->assertNull($bought['cost']);
        $this->assertNull($bought['profit']);
    }

    // ------------------------------------------------------------------- the API

    public function test_the_report_api_returns_the_profit_for_the_selected_range(): void
    {
        $item = $this->item('Rice', 'count', 'bag');
        $this->recordLine($item, 'sale', 2, 'bag', 9000, self::ANCHOR, 5000);

        $this->actingAs($this->user());

        $this->getJson('/api/reports?mode=d&anchor='.self::ANCHOR)
            ->assertOk()
            ->assertJsonPath('data.sales', 18000)
            ->assertJsonPath('data.profit.revenue', 18000)
            ->assertJsonPath('data.profit.costOfGoods', 5000)
            ->assertJsonPath('data.profit.profit', 13000)
            ->assertJsonPath('data.profit.unknownLines', 0);

        // The same figures for a range that covers the sale.
        $this->getJson('/api/reports?mode=d&start_date=2026-10-01&end_date=2026-10-07')
            ->assertOk()
            ->assertJsonPath('data.profit.profit', 13000);

        // A range that misses it reports no sales, and so no profit.
        $this->getJson('/api/reports?mode=d&start_date=2026-09-01&end_date=2026-09-07')
            ->assertOk()
            ->assertJsonPath('data.profit.profit', 0);
    }
}
