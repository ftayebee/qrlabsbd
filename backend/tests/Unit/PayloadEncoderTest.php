<?php

namespace Tests\Unit;

use App\Enums\QrType;
use App\Services\Qr\PayloadEncoder;
use PHPUnit\Framework\TestCase;

class PayloadEncoderTest extends TestCase
{
    private PayloadEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new PayloadEncoder;
    }

    public function test_wifi_escapes_reserved_characters(): void
    {
        $payload = $this->encoder->encode(QrType::Wifi, ['ssid' => 'Café;Guest', 'security' => 'WPA', 'password' => 'p:a,s"s\\1']);
        $this->assertSame('WIFI:T:WPA;S:Café\;Guest;P:p\:a\,s\"s\\\\1;;', $payload);
    }

    public function test_open_wifi_omits_password(): void
    {
        $this->assertSame('WIFI:T:nopass;S:Lobby;H:true;;', $this->encoder->encode(QrType::Wifi, ['ssid' => 'Lobby', 'security' => 'nopass', 'password' => 'ignored', 'hidden' => true]));
    }

    public function test_email_encodes_subject_and_body(): void
    {
        $this->assertSame(
            'mailto:hello%40example.com?subject=Order%20%2342%20%26%20more&body=Line%201%0ALine%202',
            $this->encoder->encode(QrType::Email, ['to' => 'hello@example.com', 'subject' => 'Order #42 & more', 'body' => "Line 1\nLine 2"]),
        );
    }

    public function test_sms_and_phone(): void
    {
        $this->assertSame('SMSTO:+4420794600:Hi there', $this->encoder->encode(QrType::Sms, ['phone' => '+44 (20) 7946-00', 'message' => 'Hi there']));
        $this->assertSame('tel:+15551234567', $this->encoder->encode(QrType::Phone, ['phone' => '+1 555 123 4567']));
    }

    public function test_vcard_escapes_values_and_uses_crlf(): void
    {
        $vcard = $this->encoder->encode(QrType::Contact, ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'company' => 'Analytical, Engines; Ltd', 'email' => 'ada@example.com']);
        $this->assertStringStartsWith("BEGIN:VCARD\r\nVERSION:3.0\r\nN:Lovelace;Ada;;;\r\nFN:Ada Lovelace\r\n", $vcard);
        $this->assertStringContainsString('ORG:Analytical\, Engines\; Ltd', $vcard);
        $this->assertStringEndsWith("END:VCARD", $vcard);
    }

    public function test_url_gets_scheme_and_app_prefers_fallback(): void
    {
        $this->assertSame('https://example.com/a', $this->encoder->encode(QrType::Url, ['url' => 'example.com/a']));
        $this->assertSame('https://apps.apple.com/app/id1', $this->encoder->encode(QrType::App, ['name' => 'X', 'fallbackUrl' => '', 'iosUrl' => 'https://apps.apple.com/app/id1']));
    }

    public function test_hosted_types_have_no_static_payload(): void
    {
        $this->assertNull($this->encoder->encode(QrType::MultiUrl, ['title' => 'x', 'links' => []]));
    }
}
