<?php

namespace App\Services\Billing\Gateway;

final class CheckoutResult
{
    public function __construct(
        public readonly string $id,
        public readonly bool $complete,
        public readonly ?string $providerSubscriptionId,
        public readonly ?string $customerId,
    ) {}
}
