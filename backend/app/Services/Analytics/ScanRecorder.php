<?php

namespace App\Services\Analytics;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\ScanAggregate;
use App\Models\UsageRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Persists one scan and rolls it into daily aggregates so dashboards never
 * scan the raw events table.
 *
 * Unique scans are an estimate: one visitor (same network address and
 * browser) counts once per code per UTC day. Bots and link-preview crawlers
 * are stored with is_bot=1 and excluded from every total.
 */
class ScanRecorder
{
    public function record(ScanContext $ctx): void
    {
        $qr = QrCode::query()->select(['id', 'user_id'])->find($ctx->qrCodeId);
        if (! $qr) {
            return;
        }

        $date = substr($ctx->scannedAt, 0, 10);
        $unique = ! $ctx->bot && Cache::add("scan:u:{$qr->id}:{$ctx->visitorHash}", 1, now()->endOfDay());

        DB::transaction(function () use ($qr, $ctx, $date, $unique) {
            QrScan::query()->insert([
                'qr_code_id' => $qr->id,
                'scanned_at' => $ctx->scannedAt,
                'country_code' => $ctx->country,
                'region' => $ctx->region,
                'device_type' => $ctx->device,
                'os' => $ctx->os,
                'browser' => $ctx->browser,
                'referrer_host' => $ctx->referrerHost,
                'visitor_hash' => $ctx->visitorHash,
                'is_unique' => $unique,
                'is_bot' => $ctx->bot,
            ]);

            $dimensions = $ctx->bot
                ? ['bot' => $ctx->browser]
                : [
                    'total' => '',
                    'device' => $ctx->device,
                    'os' => $ctx->os,
                    'browser' => $ctx->browser,
                    'country' => $ctx->country ?? 'unknown',
                    'referrer' => $ctx->referrerHost ?? 'direct',
                ];

            $rows = [];
            foreach ($dimensions as $dimension => $value) {
                $rows[] = [
                    'qr_code_id' => $qr->id, 'user_id' => $qr->user_id, 'date' => $date,
                    'dimension' => $dimension, 'value' => $value, 'scans' => 1, 'unique_scans' => $unique ? 1 : 0,
                ];
            }
            ScanAggregate::query()->upsert($rows, ['qr_code_id', 'date', 'dimension', 'value'], [
                'scans' => DB::raw('scans + 1'),
                'unique_scans' => DB::raw('unique_scans + '.($unique ? 1 : 0)),
            ]);

            if ($ctx->bot) {
                return;
            }

            QrCode::query()->whereKey($qr->id)->update([
                'total_scans' => DB::raw('total_scans + 1'),
                'unique_scans' => DB::raw('unique_scans + '.($unique ? 1 : 0)),
                'last_scanned_at' => $ctx->scannedAt,
            ]);

            UsageRecord::query()->upsert(
                [['user_id' => $qr->user_id, 'metric' => 'scans', 'period' => substr($date, 0, 7), 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]],
                ['user_id', 'metric', 'period'],
                ['quantity' => DB::raw('quantity + 1'), 'updated_at' => now()],
            );
        });
    }
}
