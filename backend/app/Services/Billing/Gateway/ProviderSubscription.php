<?php

namespace App\Services\Billing\Gateway;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;

/** Provider-neutral view of a subscription, as reported by the gateway. */
final class ProviderSubscription
{
    /** @param array<string, string> $metadata */
    public function __construct(
        public readonly string $id,
        public readonly SubscriptionStatus $status,
        public readonly ?string $customerId,
        public readonly ?CarbonImmutable $currentPeriodStart,
        public readonly ?CarbonImmutable $currentPeriodEnd,
        public readonly ?CarbonImmutable $trialEnd,
        public readonly bool $cancelAtPeriodEnd,
        public readonly ?CarbonImmutable $canceledAt,
        public readonly ?CarbonImmutable $endedAt,
        public readonly array $metadata = [],
    ) {}
}
