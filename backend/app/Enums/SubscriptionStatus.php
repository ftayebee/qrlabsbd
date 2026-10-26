<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Incomplete = 'incomplete';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /** Statuses that grant the plan's entitlements. Past-due keeps access while the gateway retries. */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    /** @return list<string> */
    public static function accessValues(): array
    {
        return [self::Trialing->value, self::Active->value, self::PastDue->value];
    }
}
