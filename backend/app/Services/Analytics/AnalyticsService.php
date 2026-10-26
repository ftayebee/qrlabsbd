<?php

namespace App\Services\Analytics;

use App\Models\ScanAggregate;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Read side of analytics. Every query hits scan_aggregates, never qr_scans. */
class AnalyticsService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function range(User $user, ?string $from, ?string $to): array
    {
        $days = $this->entitlements->for($user)->limit('analytics_days');
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->subDays(29)->startOfDay();
        if ($days > 0) {
            $earliest = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
            $start = $start->lt($earliest) ? $earliest : $start;
        }
        if ($end->lt($start)) {
            $end = $start->endOfDay();
        }
        if ($start->diffInDays($end) > 730) {
            $start = $end->subDays(730)->startOfDay();
        }

        return [$start, $end];
    }

    /**
     * @param  list<int>|null  $qrIds  Restrict to these codes (already authorised), or all of the user's.
     * @return array<string, mixed>
     */
    public function report(User $user, ?array $qrIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = fn (): Builder => ScanAggregate::query()
            ->where('user_id', $user->id)
            ->when($qrIds !== null, fn ($q) => $q->whereIn('qr_code_id', $qrIds ?: [0]))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        $seriesRows = $base()->where('dimension', 'total')
            ->groupBy('date')->orderBy('date')
            ->get([DB::raw('date'), DB::raw('SUM(scans) as scans'), DB::raw('SUM(unique_scans) as unique_scans')])
            ->keyBy(fn ($r) => substr((string) $r->getRawOriginal('date'), 0, 10));

        $series = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $row = $seriesRows->get($key);
            $series[] = ['date' => $key, 'scans' => (int) ($row->scans ?? 0), 'uniqueScans' => (int) ($row->unique_scans ?? 0)];
        }

        $breakdown = fn (string $dimension, int $limit = 10) => $base()->where('dimension', $dimension)
            ->groupBy('value')->orderByDesc('scans')->limit($limit)
            ->get(['value', DB::raw('SUM(scans) as scans'), DB::raw('SUM(unique_scans) as unique_scans')])
            ->map(fn ($r) => ['value' => (string) $r->value, 'scans' => (int) $r->scans, 'uniqueScans' => (int) $r->unique_scans])
            ->values()->all();

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'scans' => array_sum(array_column($series, 'scans')),
                'uniqueScans' => array_sum(array_column($series, 'uniqueScans')),
                'botScansExcluded' => (int) $base()->where('dimension', 'bot')->sum('scans'),
            ],
            'series' => $series,
            'devices' => $breakdown('device'),
            'os' => $breakdown('os'),
            'browsers' => $breakdown('browser'),
            'countries' => $breakdown('country', 20),
            'referrers' => $breakdown('referrer'),
        ];
    }

    /** @return list<array{campaignId: int|null, name: string, color: string|null, scans: int, uniqueScans: int, codes: int}> */
    public function campaignComparison(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('scan_aggregates as a')
            ->join('qr_codes as q', 'q.id', '=', 'a.qr_code_id')
            ->leftJoin('campaigns as c', 'c.id', '=', 'q.campaign_id')
            ->where('a.user_id', $user->id)
            ->where('a.dimension', 'total')
            ->whereBetween('a.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('q.campaign_id', 'c.name', 'c.color')
            ->orderByDesc(DB::raw('SUM(a.scans)'))
            ->get(['q.campaign_id', 'c.name', 'c.color', DB::raw('SUM(a.scans) as scans'), DB::raw('SUM(a.unique_scans) as unique_scans'), DB::raw('COUNT(DISTINCT q.id) as codes')])
            ->map(fn ($r) => [
                'campaignId' => $r->campaign_id ? (int) $r->campaign_id : null,
                'name' => $r->name ?? 'No campaign',
                'color' => $r->color,
                'scans' => (int) $r->scans,
                'uniqueScans' => (int) $r->unique_scans,
                'codes' => (int) $r->codes,
            ])->all();
    }

    /**
     * Daily rows for CSV export.
     *
     * @param  list<int>|null  $qrIds
     * @return \Generator<int, array<int, string|int>>
     */
    public function exportRows(User $user, ?array $qrIds, CarbonImmutable $from, CarbonImmutable $to): \Generator
    {
        yield ['date', 'qr_code_id', 'qr_code_name', 'dimension', 'value', 'scans', 'unique_scans_estimate'];

        $query = DB::table('scan_aggregates as a')
            ->join('qr_codes as q', 'q.id', '=', 'a.qr_code_id')
            ->where('a.user_id', $user->id)
            ->where('a.dimension', '!=', 'bot')
            ->when($qrIds !== null, fn ($q) => $q->whereIn('a.qr_code_id', $qrIds ?: [0]))
            ->whereBetween('a.date', [$from->toDateString(), $to->toDateString()])
            ->select(['a.id', 'a.date', 'a.qr_code_id', 'q.name', 'a.dimension', 'a.value', 'a.scans', 'a.unique_scans']);

        foreach ($query->lazyById(1000, 'a.id', 'id') as $r) {
            yield [substr((string) $r->date, 0, 10), (int) $r->qr_code_id, self::csvSafe((string) $r->name), $r->dimension, self::csvSafe((string) $r->value), (int) $r->scans, (int) $r->unique_scans];
        }
    }

    /** Neutralises spreadsheet formula injection in user-controlled cells. */
    public static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
