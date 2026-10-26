<?php

namespace App\Services\Billing\Gateway;

final class WebhookPayload
{
    /** @param array<string, mixed> $data The event's object, provider-specific. */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
        public readonly string $raw,
    ) {}
}
