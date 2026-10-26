<?php

namespace App\Http\Requests\Qr;

use App\Enums\QrType;
use App\Services\Qr\PayloadEncoder;
use App\Services\Qr\UrlSafety;
use Closure;
use Illuminate\Validation\Rule;

/** Validation rules for the `content` object of each QR type. Mirrors frontend/app/utils/qr/validation.ts. */
class QrContentRules
{
    /** @return array<string, mixed> */
    public static function for(QrType $type, bool $dynamic): array
    {
        $safeUrl = self::safeUrl();
        $url = ['string', 'max:2048', $safeUrl];
        $phone = ['string', 'regex:/^\+?[\d\s().\-]{5,24}$/'];
        $social = ['instagram', 'facebook', 'x', 'linkedin', 'tiktok', 'youtube', 'github', 'whatsapp', 'telegram', 'pinterest', 'threads', 'website'];

        $contact = [
            'content.firstName' => ['required', 'string', 'max:80'],
            'content.lastName' => ['nullable', 'string', 'max:80'],
            'content.company' => ['nullable', 'string', 'max:120'],
            'content.jobTitle' => ['nullable', 'string', 'max:120'],
            'content.phone' => ['nullable', ...$phone],
            'content.mobile' => ['nullable', ...$phone],
            'content.email' => ['nullable', 'email:rfc', 'max:190'],
            'content.website' => ['nullable', ...$url],
            'content.street' => ['nullable', 'string', 'max:160'],
            'content.city' => ['nullable', 'string', 'max:80'],
            'content.region' => ['nullable', 'string', 'max:80'],
            'content.postcode' => ['nullable', 'string', 'max:20'],
            'content.country' => ['nullable', 'string', 'max:80'],
            'content.note' => ['nullable', 'string', 'max:300'],
        ];

        return match ($type) {
            QrType::Url => ['content.url' => ['required', ...$url]],
            QrType::Text => ['content.text' => ['required', 'string', 'max:1200']],
            QrType::Phone => ['content.phone' => ['required', ...$phone]],
            QrType::Sms => ['content.phone' => ['required', ...$phone], 'content.message' => ['nullable', 'string', 'max:300']],
            QrType::Email => [
                'content.to' => ['required', 'string', 'max:500', self::emailList()],
                'content.subject' => ['nullable', 'string', 'max:200'],
                'content.body' => ['nullable', 'string', 'max:600'],
            ],
            QrType::Wifi => [
                'content.ssid' => ['required', 'string', 'max:32'],
                'content.security' => ['required', Rule::in(['WPA', 'WEP', 'nopass'])],
                'content.password' => ['nullable', 'string', 'max:63', 'required_if:content.security,WPA,WEP', function (string $a, mixed $v, Closure $fail) {
                    if (request('content.security') === 'WPA' && strlen((string) $v) < 8) {
                        $fail('WPA passwords are 8–63 characters.');
                    }
                }],
                'content.hidden' => ['boolean'],
            ],
            QrType::Contact => $contact,
            QrType::BusinessCard => $contact + [
                'content.headline' => ['nullable', 'string', 'max:120'],
                'content.bio' => ['nullable', 'string', 'max:600'],
                'content.accentColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'content.links' => ['array', 'max:10'],
                'content.links.*.label' => ['required', 'string', 'max:60'],
                'content.links.*.url' => ['required', ...$url],
            ],
            QrType::App => [
                'content.name' => ['required', 'string', 'max:80'],
                'content.description' => ['nullable', 'string', 'max:300'],
                'content.iosUrl' => ['nullable', 'required_without_all:content.androidUrl,content.fallbackUrl', ...$url],
                'content.androidUrl' => ['nullable', ...$url],
                'content.fallbackUrl' => ['nullable', ...$url],
            ],
            QrType::MultiUrl => [
                'content.title' => ['required', 'string', 'max:100'],
                'content.description' => ['nullable', 'string', 'max:300'],
                'content.links' => ['required', 'array', 'min:1', 'max:20'],
                'content.links.*.label' => ['required', 'string', 'max:60'],
                'content.links.*.url' => ['required', ...$url],
            ],
            QrType::Social => [
                'content.title' => ['required', 'string', 'max:100'],
                'content.description' => ['nullable', 'string', 'max:300'],
                'content.links' => ['required', 'array', 'min:1', 'max:15'],
                'content.links.*.platform' => ['required', Rule::in($social)],
                'content.links.*.url' => ['required', ...$url],
            ],
            QrType::Pdf => [
                'content.title' => ['required', 'string', 'max:120'],
                'content.description' => ['nullable', 'string', 'max:300'],
                'content.fileId' => ['required', 'integer', Rule::exists('qr_files', 'id')->where('user_id', request()->user()?->id)->where('purpose', 'pdf')->whereNull('deleted_at')],
                'content.fileName' => ['nullable', 'string', 'max:200'],
            ],
        };
    }

    private static function safeUrl(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || $value === '') {
                return;
            }
            $error = app(UrlSafety::class)->check(PayloadEncoder::normalizeUrl($value));
            if ($error) {
                $fail($error);
            }
        };
    }

    private static function emailList(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            foreach (explode(',', (string) $value) as $addr) {
                if (! filter_var(trim($addr), FILTER_VALIDATE_EMAIL)) {
                    $fail('Enter valid email addresses separated by commas.');

                    return;
                }
            }
        };
    }
}
