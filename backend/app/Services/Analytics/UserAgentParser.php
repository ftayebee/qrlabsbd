<?php

namespace App\Services\Analytics;

/**
 * Coarse user-agent classification. Only categories are stored, never the
 * raw user-agent string.
 */
class UserAgentParser
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|quora link|whatsapp|telegrambot|discord|skypeuripreview|vkshare|bitlybot|curl|wget|python-requests|httpclient|okhttp|go-http-client|axios|node-fetch|headless|lighthouse|pingdom|uptime|monitor/i';

    /** @return array{device: string, os: string, browser: string, bot: bool} */
    public function parse(?string $ua): array
    {
        $ua = (string) $ua;
        $bot = $ua === '' || (bool) preg_match(self::BOT_PATTERN, $ua);

        return [
            'device' => $bot ? 'bot' : $this->device($ua),
            'os' => $this->os($ua),
            'browser' => $bot ? 'Bot' : $this->browser($ua),
            'bot' => $bot,
        ];
    }

    private function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|Nexus (7|9|10)|SM-T|Kindle|Silk|(Android(?!.*Mobile))/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/Windows NT|Macintosh|X11|CrOS|Linux x86_64/i', $ua)) {
            return 'desktop';
        }

        return 'other';
    }

    private function os(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Other',
        };
    }

    private function browser(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/FBAN|FBAV|Instagram|Line\/|MicroMessenger|LinkedInApp|Snapchat|TikTok/i', $ua) => 'In-app',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => 'Other',
        };
    }

    public function isIos(?string $ua): bool
    {
        return (bool) preg_match('/iPhone|iPad|iPod/i', (string) $ua);
    }

    public function isAndroid(?string $ua): bool
    {
        return (bool) preg_match('/Android/i', (string) $ua);
    }
}
