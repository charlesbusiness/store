<?php

namespace App\Http\Controllers;

use App\Enums\PeriodMode;
use App\Services\Report\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The reports tab: period figures, the trend chart and per-item tables.
 *
 * The period can be specified either as an anchor date + mode (legacy)
 * or as a custom start/end date range. Both arrive as query parameters.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $mode = $this->mode($request);
        $dateRange = $this->dateRange($request);
        $anchor = $dateRange[1]; // end date

        $summary = $this->reports->summary($mode, $anchor, $dateRange);

        return view('reports.index', [
            'summary' => $summary,
            'modes' => PeriodMode::cases(),
            'dateRange' => $dateRange,
        ]);
    }

    /** Fall back to the default grain rather than erroring on bad input. */
    private function mode(Request $request): PeriodMode
    {
        return PeriodMode::tryFrom((string) $request->query('mode', '')) ?? PeriodMode::default();
    }

    /**
     * Parse custom date range from query params.
     * Returns [start, end] as CarbonImmutable.
     * Rules:
     * - Neither provided → today only
     * - Both provided → custom range (start ≤ end)
     * - Start only → start to today
     * - End only → single day (end date)
     * - Invalid format → falls back to today
     */
    private function dateRange(Request $request): array
    {
        $startRaw = trim((string) $request->query('start_date', ''));
        $endRaw = trim((string) $request->query('end_date', ''));

        // Neither provided → today only
        if ($startRaw === '' && $endRaw === '') {
            $today = CarbonImmutable::today();
            return [$today->startOfDay(), $today->endOfDay()];
        }

        // Both provided → custom range
        if ($startRaw !== '' && $endRaw !== '') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $startRaw)->startOfDay();
                $end = CarbonImmutable::createFromFormat('Y-m-d', $endRaw)->endOfDay();
                if ($start <= $end) {
                    return [$start, $end];
                }
            } catch (\Throwable) {
                // Invalid format, fall through to today
            }
        }

        // Start only → start to today
        if ($startRaw !== '') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $startRaw)->startOfDay();
                $end = CarbonImmutable::today()->endOfDay();
                if ($start <= $end) {
                    return [$start, $end];
                }
            } catch (\Throwable) {
                // Invalid format, fall through to today
            }
        }

        // End only → single day
        if ($endRaw !== '') {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d', $endRaw);
                return [$end->startOfDay(), $end->endOfDay()];
            } catch (\Throwable) {
                // Invalid format, fall through to today
            }
        }

        // Fallback: today
        $today = CarbonImmutable::today();
        return [$today->startOfDay(), $today->endOfDay()];
    }
}
