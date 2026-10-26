<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly EntitlementService $entitlements,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->analytics->range($request->user(), $filters['from'] ?? null, $filters['to'] ?? null);
        $ids = isset($filters['campaign_id'])
            ? $request->user()->qrCodes()->where('campaign_id', $filters['campaign_id'])->pluck('id')->all()
            : null;

        return response()->json(['data' => $this->analytics->report($request->user(), $ids, $from, $to) + $this->meta($request)]);
    }

    public function qrCode(Request $request, QrCode $qrCode): JsonResponse
    {
        $this->authorize('view', $qrCode);
        $filters = $this->filters($request);
        $owner = $qrCode->user;
        [$from, $to] = $this->analytics->range($owner, $filters['from'] ?? null, $filters['to'] ?? null);

        return response()->json(['data' => $this->analytics->report($owner, [$qrCode->id], $from, $to) + $this->meta($request)]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        [$from, $to] = $this->analytics->range($request->user(), $filters['from'] ?? null, $filters['to'] ?? null);

        return response()->json(['data' => $this->analytics->campaignComparison($request->user(), $from, $to)]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->entitlements->for($user)->allows('csv_export'), 402, 'CSV export is available on paid plans.');
        $filters = $this->filters($request);
        [$from, $to] = $this->analytics->range($user, $filters['from'] ?? null, $filters['to'] ?? null);
        $ids = null;
        if (isset($filters['qr_code_id'])) {
            $qr = QrCode::query()->findOrFail($filters['qr_code_id']);
            $this->authorize('view', $qr);
            $ids = [$qr->id];
        }

        return response()->streamDownload(function () use ($user, $ids, $from, $to) {
            $out = fopen('php://output', 'w');
            foreach ($this->analytics->exportRows($user, $ids, $from, $to) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "scans-{$from->toDateString()}-to-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'campaign_id' => ['nullable', 'integer'],
            'qr_code_id' => ['nullable', 'integer'],
        ]);
    }

    /** @return array<string, mixed> */
    private function meta(Request $request): array
    {
        return [
            'historyDays' => $this->entitlements->for($request->user())->limit('analytics_days'),
            'definitions' => [
                'scans' => 'Every scan by a person, excluding detected bots and link-preview crawlers.',
                'uniqueScans' => 'Estimated distinct visitors per code per day, based on a salted daily hash of network address and browser. Not a count of people: shared networks undercount and changing networks overcount.',
            ],
        ];
    }
}
