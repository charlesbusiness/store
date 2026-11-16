<?php

namespace App\Http\Controllers\Api;

use App\Enums\PeriodMode;
use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use App\Support\TenantAuthorizer;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly TenantContext $context,
        private readonly TenantAuthorizer $authorizer,
    ) {}

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

        [$scope, $branchId] = $this->scope($request);

        $summary = $this->reports->summary(
            $mode,
            $anchor,
            $hasCustomRange ? $dateRange : null,
            $scope,
            $branchId,
        );

        $branches = $this->authorizer->allowedBranches($request->user(), $this->context->tenant());

        if ($scope === 'account' && $branches->count() > 1) {
            $summary = $summary->withBranchBreakdown(
                $this->reports->branchBreakdown(
                    $mode,
                    $anchor,
                    $hasCustomRange ? $dateRange : null,
                    $branches,
                    $scope === 'branch' ? $branchId : null,
                )
            );
        }

        return response()->json(['data' => $summary]);
    }

    public function trend(Request $request): JsonResponse
    {
        $mode = PeriodMode::tryFrom((string) $request->query('mode', 'daily')) ?? PeriodMode::default();
        $anchor = $request->query('anchor') 
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->query('anchor')) 
            : CarbonImmutable::today();
        
        [$scope, $branchId] = $this->scope($request);

        $trend = $this->reports->trend($mode, $anchor, $scope, $branchId);

        return response()->json(['data' => $trend]);
    }

    /** @return array{0: string, 1: ?int} */
    private function scope(Request $request): array
    {
        $requested = (string) $request->query('scope', '');
        $scope = in_array($requested, ['account', 'branch'], true)
            ? $requested
            : $this->context->tenant()->reporting_mode->value;

        return [$scope, $scope === 'branch' ? $this->context->branchId() : null];
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
