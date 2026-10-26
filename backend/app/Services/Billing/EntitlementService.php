<?php

namespace App\Services\Billing;

use App\Enums\QrMode;
use App\Enums\QrStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\QrFile;
use App\Models\QuotaOverride;
use App\Models\Subscription;
use App\Models\TeamMember;
use App\Models\UsageRecord;
use App\Models\User;

/**
 * Resolves entitlements from the database (never from the client) and
 * implements the lapsed-subscription policy:
 *
 *  1. Active, trialing and past-due subscriptions grant the plan in full.
 *  2. When a paid subscription ends, the plan stays in effect until
 *     grace_ends_at (BILLING_GRACE_DAYS after the end).
 *  3. After grace the account falls back to the default (free) plan. Existing
 *     dynamic codes KEEP REDIRECTING; codes above the free limit become
 *     read-only (no destination edits) and new dynamic codes are blocked
 *     until the account is back within limits or upgrades.
 */
class EntitlementService
{
    /** @var array<int, Entitlements> */
    private array $memo = [];

    public function for(User $user): Entitlements
    {
        return $this->memo[$user->id] ??= $this->resolve($user);
    }

    public function forget(User $user): void
    {
        unset($this->memo[$user->id]);
    }

    private function resolve(User $user): Entitlements
    {
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', SubscriptionStatus::accessValues())
            ->with('plan.features')
            ->latest('id')
            ->first();

        $inGrace = false;
        $graceEndsAt = null;

        if (! $subscription) {
            $lapsed = Subscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [SubscriptionStatus::Canceled->value, SubscriptionStatus::Expired->value])
                ->where('grace_ends_at', '>', now())
                ->with('plan.features')
                ->latest('id')
                ->first();
            if ($lapsed) {
                $subscription = $lapsed;
                $inGrace = true;
                $graceEndsAt = $lapsed->grace_ends_at;
            }
        }

        $plan = $subscription?->plan ?? Plan::query()->with('features')->where('is_default', true)->first() ?? new Plan(['name' => 'Free', 'slug' => 'free']);

        $values = $plan->relationLoaded('features')
            ? $plan->features->mapWithKeys(fn (PlanFeature $f) => [$f->key => $f->typedValue()])->all()
            : [];

        QuotaOverride::query()
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->each(function (QuotaOverride $o) use (&$values) {
                $default = Entitlements::DEFAULTS[$o->key] ?? null;
                $values[$o->key] = is_bool($default) ? filter_var($o->value, FILTER_VALIDATE_BOOLEAN) : (is_int($default) ? (int) $o->value : $o->value);
            });

        return new Entitlements($plan, $inGrace ? null : $subscription, $values, $inGrace, $graceEndsAt);
    }

    public function dynamicCount(User $user): int
    {
        return $user->qrCodes()->where('mode', QrMode::Dynamic)->where('status', '!=', QrStatus::Archived)->count();
    }

    public function savedStaticCount(User $user): int
    {
        return $user->qrCodes()->where('mode', QrMode::Static)->count();
    }

    public function storageBytes(User $user): int
    {
        return (int) QrFile::query()->where('user_id', $user->id)->sum('size');
    }

    public function monthlyScans(User $user): int
    {
        return (int) UsageRecord::query()->where(['user_id' => $user->id, 'metric' => 'scans', 'period' => now()->format('Y-m')])->value('quantity');
    }

    /** Dynamic codes above the plan limit are read-only (they still redirect). */
    public function isOverDynamicLimit(User $user): bool
    {
        $e = $this->for($user);

        return ! $e->isUnlimited('dynamic_codes') && $this->dynamicCount($user) > $e->limit('dynamic_codes');
    }

    /** @return array<string, int> */
    public function usage(User $user): array
    {
        return [
            'dynamic_codes' => $this->dynamicCount($user),
            'saved_static_codes' => $this->savedStaticCount($user),
            'scans_per_month' => $this->monthlyScans($user),
            'storage_mb' => (int) ceil($this->storageBytes($user) / 1048576),
            'storage_bytes' => $this->storageBytes($user),
            'team_members' => TeamMember::query()->where('owner_id', $user->id)->count(),
        ];
    }
}
