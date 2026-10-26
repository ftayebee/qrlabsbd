<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;

/**
 * The privacy-reduced facts about one scan. Built in the request so that the
 * raw IP address never leaves the web process: only a salted, daily-rotating
 * hash is queued, and it is used solely to estimate unique scans.
 */
final class ScanContext
{
    public function __construct(
        public readonly int $qrCodeId,
        public readonly string $scannedAt,
        public readonly string $device,
        public readonly string $os,
        public readonly string $browser,
        public readonly bool $bot,
        public readonly ?string $country,
        public readonly ?string $region,
        public readonly ?string $referrerHost,
        public readonly string $visitorHash,
    ) {}

    public static function fromRequest(Request $request, int $qrCodeId, UserAgentParser $parser): self
    {
        $ua = $parser->parse($request->userAgent());
        $day = now()->toDateString();
        $hash = hash_hmac('sha256', implode('|', [$request->ip(), (string) $request->userAgent(), $qrCodeId, $day]), (string) config('app.key'));

        $country = strtoupper((string) $request->header((string) config('qrio.analytics.country_header')));
        $regionHeader = config('qrio.analytics.region_header');
        $region = $regionHeader ? mb_substr((string) $request->header($regionHeader), 0, 80) : null;

        $refHost = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return new self(
            qrCodeId: $qrCodeId,
            scannedAt: now()->toDateTimeString(),
            device: $ua['device'],
            os: $ua['os'],
            browser: $ua['browser'],
            bot: $ua['bot'],
            country: preg_match('/^[A-Z]{2}$/', $country) && ! in_array($country, ['XX', 'T1'], true) ? $country : null,
            region: $region ?: null,
            referrerHost: is_string($refHost) ? mb_substr(strtolower($refHost), 0, 190) : null,
            visitorHash: $hash,
        );
    }
}
