<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonInterface;

/** Immutable snapshot of what a user's current plan allows. */
final class Entitlements
{
    /** Feature keys understood by the application, with free-tier fallbacks. */
    public const DEFAULTS = [
        'dynamic_codes' => 2,
        'saved_static_codes' => 25,
        'scans_per_month' => 1000,
        'storage_mb' => 10,
        'max_upload_mb' => 5,
        'analytics_days' => 30,
        'team_members' => 0,
        'campaigns' => false,
        'custom_logo' => false,
        'vector_downloads' => false,
        'csv_export' => false,
        'remove_branding' => false,
    ];

    /** @param array<string, int|bool|string> $values */
    public function __construct(
        public readonly Plan $plan,
        public readonly ?Subscription $subscription,
        private readonly array $values,
        public readonly bool $inGrace = false,
        public readonly ?CarbonInterface $graceEndsAt = null,
    ) {}

    public function get(string $key): int|bool|string
    {
        return $this->values[$key] ?? self::DEFAULTS[$key] ?? false;
    }

    /** -1 means unlimited. */
    public function limit(string $key): int
    {
        return (int) $this->get($key);
    }

    public function isUnlimited(string $key): bool
    {
        return $this->limit($key) < 0;
    }

    public function allows(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function withinLimit(string $key, int $currentUsage, int $adding = 1): bool
    {
        return $this->isUnlimited($key) || $currentUsage + $adding <= $this->limit($key);
    }

    /** @return array<string, int|bool|string> */
    public function toArray(): array
    {
        return array_merge(self::DEFAULTS, $this->values);
    }
}
