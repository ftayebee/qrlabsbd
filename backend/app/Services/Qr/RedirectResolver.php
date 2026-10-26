<?php

namespace App\Services\Qr;

use App\Enums\QrMode;
use App\Enums\QrStatus;
use App\Enums\QrType;
use App\Models\QrCode;
use App\Models\QrDestination;
use App\Services\Analytics\UserAgentParser;
use Illuminate\Support\Facades\Cache;

/**
 * Hot path for every scan. Resolution data is cached per short code so a scan
 * costs one cache read; the cache is invalidated whenever the code changes.
 */
class RedirectResolver
{
    public function __construct(private readonly UserAgentParser $ua, private readonly UrlSafety $safety) {}

    /** @return array{id: int, status: string, type: string, blocked: bool, owner_suspended: bool, destinations: array<string, string>}|null */
    public function lookup(string $code): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{4,16}$/', $code)) {
            return null;
        }

        $cached = Cache::remember(QrCode::cacheKeyFor($code), (int) config('qrio.redirects.cache_ttl'), function () use ($code) {
            $qr = QrCode::query()
                ->where('short_code', $code)
                ->where('mode', QrMode::Dynamic)
                ->with(['destinations', 'user:id,status'])
                ->first();
            if (! $qr) {
                return ['missing' => true];
            }

            $destinations = $qr->destinations
                ->filter(fn (QrDestination $d) => in_array($d->kind, ['primary', 'ios', 'android', 'fallback'], true))
                ->mapWithKeys(fn (QrDestination $d) => [$d->kind => $d->url])->all();

            // Re-checked on every cache fill so newly blocked hosts stop redirecting within the cache TTL.
            $unsafe = collect($destinations)->contains(fn (string $url) => ! $this->safety->isSafe($url));

            return [
                'id' => $qr->id,
                'status' => $qr->status->value,
                'type' => $qr->type->value,
                'blocked' => $qr->moderation_status === 'blocked' || $unsafe,
                'owner_suspended' => $qr->user?->status === 'suspended',
                'destinations' => $destinations,
            ];
        });

        return isset($cached['missing']) ? null : $cached;
    }

    /**
     * Decides where a scan goes. Returns an absolute URL, or null when the code
     * is unavailable and a status page should be shown instead.
     *
     * @param  array{id: int, status: string, type: string, blocked: bool, owner_suspended: bool, destinations: array<string, string>}  $entry
     */
    public function target(array $entry, string $code, ?string $userAgent): ?string
    {
        if ($entry['blocked'] || $entry['owner_suspended'] || $entry['status'] !== QrStatus::Active->value) {
            return null;
        }

        $hosted = rtrim((string) config('app.frontend_url'), '/').'/p/'.$code;
        $d = $entry['destinations'];

        return match (QrType::from($entry['type'])) {
            QrType::Url => $d['primary'] ?? null,
            QrType::App => match (true) {
                $this->ua->isIos($userAgent) && isset($d['ios']) => $d['ios'],
                $this->ua->isAndroid($userAgent) && isset($d['android']) => $d['android'],
                default => $d['fallback'] ?? $hosted,
            },
            default => $hosted,
        };
    }
}
