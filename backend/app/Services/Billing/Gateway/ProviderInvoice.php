<?php

namespace App\Services\Billing\Gateway;

use Carbon\CarbonImmutable;

final class ProviderInvoice
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $subscriptionId,
        public readonly ?string $customerId,
        public readonly ?string $number,
        public readonly string $status,
        public readonly string $currency,
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly int $tax,
        public readonly int $total,
        public readonly ?CarbonImmutable $periodStart,
        public readonly ?CarbonImmutable $periodEnd,
        public readonly ?CarbonImmutable $issuedAt,
        public readonly ?CarbonImmutable $paidAt,
        public readonly ?string $hostedUrl,
        public readonly ?string $pdfUrl,
        public readonly ?string $paymentId,
        public readonly int $attempt,
        public readonly ?string $failureReason,
    ) {}
}
