<?php

namespace App\Services\Qr;

use App\Services\SettingsService;

/**
 * Validates destinations used by dynamic QR codes and hosted pages.
 *
 * The API never fetches these URLs server-side, so there is no SSRF surface;
 * these rules prevent open-redirect abuse, redirect loops through our own
 * short domain, and links to non-web schemes or internal hosts.
 */
class UrlSafety
{
    public function __construct(private readonly SettingsService $settings) {}

    public function check(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            return 'Enter a URL up to 2,048 characters.';
        }
        if (preg_match('/[\s\x00-\x1F\x7F]/', $url)) {
            return 'URLs cannot contain spaces or control characters.';
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return 'Enter a full URL starting with https://';
        }
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return 'Only http and https links are allowed.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Links containing credentials are not allowed.';
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($this->isInternalHost($host)) {
            return 'Links to local or private network addresses are not allowed.';
        }
        if ($this->pointsAtShortDomain($host, $parts['path'] ?? '')) {
            return 'A dynamic code cannot point to another dynamic code. Use the final destination instead.';
        }
        if ($this->isBlocked($host)) {
            return 'This destination has been blocked for safety reasons.';
        }

        return null;
    }

    public function isSafe(string $url): bool
    {
        return $this->check($url) === null;
    }

    private function isInternalHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            return true;
        }
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return ! str_contains($host, '.');
    }

    private function pointsAtShortDomain(string $host, string $path): bool
    {
        $shortHost = strtolower((string) parse_url((string) config('qrio.short_domain'), PHP_URL_HOST));

        return $shortHost !== '' && $host === $shortHost && str_starts_with($path, '/r/');
    }

    private function isBlocked(string $host): bool
    {
        $blocked = array_merge(
            config('qrio.redirects.blocked_hosts', []),
            (array) $this->settings->get('security.blocked_hosts', []),
        );
        foreach ($blocked as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry !== '' && ($host === $entry || str_ends_with($host, '.'.$entry))) {
                return true;
            }
        }

        return false;
    }
}
