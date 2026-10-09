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
 * The period and anchor date arrive as query parameters, so switching grain is
 * a plain GET and the whole page re-renders server-side.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $mode = $this->mode($request);
        $anchor = $this->anchor($request);

        return view('reports.index', [
            'summary' => $this->reports->summary($mode, $anchor),
            'modes' => PeriodMode::cases(),
        ]);
    }

    /** Fall back to the default grain rather than erroring on bad input. */
    private function mode(Request $request): PeriodMode
    {
        return PeriodMode::tryFrom((string) $request->query('mode', '')) ?? PeriodMode::default();
    }

    /**
     * Fall back to today rather than erroring on a hand-edited query string.
     * createFromFormat throws on nonsense input, so it is guarded explicitly.
     */
    private function anchor(Request $request): CarbonImmutable
    {
        $raw = trim((string) $request->query('anchor', ''));

        if ($raw !== '') {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
            } catch (\Throwable) {
                // Not a date at all, so fall through to today.
            }
        }

        return CarbonImmutable::today();
    }
}
