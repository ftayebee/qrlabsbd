<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QrMode;
use App\Enums\QrStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QrCodeResource;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics, EntitlementService $entitlements): JsonResponse
    {
        $user = $request->user();
        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays(29)->startOfDay();
        $report = $analytics->report($user, null, $from, $to);
        $previous = $analytics->report($user, null, $from->subDays(30), $from->subSecond());

        $counts = $user->qrCodes()
            ->selectRaw('mode, status, COUNT(*) as c')
            ->groupBy('mode', 'status')->get();

        $top = $user->qrCodes()->where('status', '!=', QrStatus::Archived)->orderByDesc('total_scans')->limit(5)->with('design')->get();
        $recent = $user->qrCodes()->latest()->limit(5)->with('design')->get();

        return response()->json(['data' => [
            'codes' => [
                'total' => (int) $counts->sum('c'),
                'dynamic' => (int) $counts->where('mode', QrMode::Dynamic)->sum('c'),
                'static' => (int) $counts->where('mode', QrMode::Static)->sum('c'),
                'active' => (int) $counts->where('status', QrStatus::Active)->sum('c'),
                'paused' => (int) $counts->where('status', QrStatus::Paused)->sum('c'),
            ],
            'scans' => [
                'last30' => $report['totals']['scans'],
                'unique30' => $report['totals']['uniqueScans'],
                'previous30' => $previous['totals']['scans'],
                'series' => $report['series'],
                'devices' => $report['devices'],
            ],
            'usage' => $entitlements->usage($user),
            'entitlements' => $entitlements->for($user)->toArray(),
            'plan' => $entitlements->for($user)->plan->name,
            'topCodes' => QrCodeResource::collection($top),
            'recentCodes' => QrCodeResource::collection($recent),
        ]]);
    }
}
