<?php

namespace App\Enums;

enum QrType: string
{
    case Url = 'url';
    case Pdf = 'pdf';
    case MultiUrl = 'multi_url';
    case Contact = 'contact';
    case Text = 'text';
    case App = 'app';
    case Sms = 'sms';
    case Email = 'email';
    case Phone = 'phone';
    case Social = 'social';
    case BusinessCard = 'business_card';
    case Wifi = 'wifi';

    /** @return list<QrMode> */
    public function supportedModes(): array
    {
        return match ($this) {
            self::Url, self::Contact, self::App => [QrMode::Static, QrMode::Dynamic],
            self::Pdf, self::MultiUrl, self::Social, self::BusinessCard => [QrMode::Dynamic],
            self::Text, self::Sms, self::Email, self::Phone, self::Wifi => [QrMode::Static],
        };
    }

    /** Hosted types resolve to a landing page rendered by the frontend at /p/{code}. */
    public function isHostedPage(): bool
    {
        return in_array($this, [self::Pdf, self::MultiUrl, self::Social, self::BusinessCard, self::Contact, self::App], true);
    }
}
