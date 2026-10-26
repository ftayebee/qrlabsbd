<?php

namespace App\Services\Billing\Gateway;

final class ProviderRefund
{
    public function __construct(
        public readonly string $id,
        public readonly string $paymentId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $reason,
    ) {}
}
