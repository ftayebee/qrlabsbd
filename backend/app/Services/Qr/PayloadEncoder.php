<?php

namespace App\Services\Qr;

use App\Enums\QrType;

/**
 * Server-side mirror of frontend/app/utils/qr/payload.ts. The server re-derives
 * the static payload from validated content so a saved code always matches
 * what was validated, regardless of what the client rendered.
 */
class PayloadEncoder
{
    /** @param array<string, mixed> $c */
    public function encode(QrType $type, array $c): ?string
    {
        return match ($type) {
            QrType::Url => self::normalizeUrl((string) $c['url']),
            QrType::Text => (string) $c['text'],
            QrType::Phone => 'tel:'.self::normalizePhone((string) $c['phone']),
            QrType::Sms => $this->sms($c),
            QrType::Email => $this->email($c),
            QrType::Wifi => $this->wifi($c),
            QrType::Contact => $this->vcard($c),
            QrType::App => self::normalizeUrl((string) (array_values(array_filter([$c['fallbackUrl'] ?? '', $c['iosUrl'] ?? '', $c['androidUrl'] ?? '']))[0] ?? '')),
            default => null,
        };
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return $url === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) ? $url : 'https://'.$url;
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        return (str_starts_with($phone, '+') ? '+' : '').preg_replace('/\D/', '', $phone);
    }

    public static function escapeWifi(string $v): string
    {
        return (string) preg_replace('/([\\\\;,:"])/', '\\\\$1', $v);
    }

    public static function escapeVCard(string $v): string
    {
        return str_replace(["\\", ',', ';', "\r\n", "\n"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $v);
    }

    /** @param array<string, mixed> $c */
    private function sms(array $c): string
    {
        $number = self::normalizePhone((string) $c['phone']);
        $message = (string) ($c['message'] ?? '');

        return $message !== '' ? "SMSTO:{$number}:{$message}" : "SMSTO:{$number}";
    }

    /** @param array<string, mixed> $c */
    private function email(array $c): string
    {
        $to = implode(',', array_map(fn ($a) => rawurlencode(trim($a)), explode(',', (string) $c['to'])));
        $params = [];
        if (! empty($c['subject'])) {
            $params[] = 'subject='.rawurlencode((string) $c['subject']);
        }
        if (! empty($c['body'])) {
            $params[] = 'body='.rawurlencode((string) $c['body']);
        }

        return 'mailto:'.$to.($params ? '?'.implode('&', $params) : '');
    }

    /** @param array<string, mixed> $c */
    private function wifi(array $c): string
    {
        $security = in_array($c['security'] ?? 'WPA', ['WPA', 'WEP'], true) ? $c['security'] : 'nopass';
        $parts = ["T:{$security}", 'S:'.self::escapeWifi((string) $c['ssid'])];
        if ($security !== 'nopass' && ! empty($c['password'])) {
            $parts[] = 'P:'.self::escapeWifi((string) $c['password']);
        }
        if (! empty($c['hidden'])) {
            $parts[] = 'H:true';
        }

        return 'WIFI:'.implode(';', $parts).';;';
    }

    /** @param array<string, mixed> $c */
    public function vcard(array $c): string
    {
        $e = fn (?string $v) => self::escapeVCard(trim((string) $v));
        $lines = ['BEGIN:VCARD', 'VERSION:3.0'];
        $lines[] = 'N:'.$e($c['lastName'] ?? '').';'.$e($c['firstName'] ?? '').';;;';
        $lines[] = 'FN:'.$e(trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? '')));
        if (! empty($c['company'])) {
            $lines[] = 'ORG:'.$e($c['company']);
        }
        if (! empty($c['jobTitle'])) {
            $lines[] = 'TITLE:'.$e($c['jobTitle']);
        }
        if (! empty($c['phone'])) {
            $lines[] = 'TEL;TYPE=WORK,VOICE:'.self::normalizePhone($c['phone']);
        }
        if (! empty($c['mobile'])) {
            $lines[] = 'TEL;TYPE=CELL:'.self::normalizePhone($c['mobile']);
        }
        if (! empty($c['email'])) {
            $lines[] = 'EMAIL;TYPE=INTERNET:'.trim($c['email']);
        }
        if (! empty($c['website'])) {
            $lines[] = 'URL:'.self::normalizeUrl($c['website']);
        }
        $addr = array_map(fn ($k) => $c[$k] ?? '', ['street', 'city', 'region', 'postcode', 'country']);
        if (array_filter($addr)) {
            $lines[] = 'ADR;TYPE=WORK:;;'.implode(';', array_map($e, $addr));
        }
        if (! empty($c['note'])) {
            $lines[] = 'NOTE:'.$e($c['note']);
        }
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }
}
