<?php

namespace App\Http\Controllers\Api;

use App\Enums\PeriodMode;
use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $mode = PeriodMode::tryFrom((string) $request->query('mode', '')) ?? PeriodMode::default();
        
        $dateRange = $this->parseDateRange($request);
        
        // If no custom date range, use anchor (today by default) to get the period bucket for the mode
        $hasCustomRange = $request->filled('start_date') || $request->filled('end_date');
        $anchor = $hasCustomRange 
            ? $dateRange[1] 
            : ($request->filled('anchor') 
                ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('anchor')) 
                : CarbonImmutable::today());

        $summary = $this->reports->summary($mode, $anchor, $hasCustomRange ? $dateRange : null);
        
        return response()->json(['data' => $summary]);
    }

    public function trend(Request $request): JsonResponse
    {
        $mode = PeriodMode::tryFrom((string) $request->query('mode', 'daily')) ?? PeriodMode::default();
        $anchor = $request->query('anchor') 
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('anchor')) 
            : CarbonImmutable::today();
        
        $trend = $this->reports->trend($mode, $anchor);
        
        return response()->json(['data' => $trend]);
    }

    private function parseDateRange(Request $request): array
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
