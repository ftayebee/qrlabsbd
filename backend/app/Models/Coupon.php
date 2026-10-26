<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'value', 'currency', 'duration', 'duration_months', 'max_redemptions',
        'starts_at', 'expires_at', 'is_active', 'provider_coupon_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'duration_months' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Returns a human-readable reason when the coupon cannot be used, or null when valid. */
    public function unusableReason(?Plan $plan = null, ?User $user = null, ?string $currency = null): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not valid yet.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return 'This coupon has reached its redemption limit.';
        }
        if ($this->type === 'fixed' && $currency && strtoupper($currency) !== $this->currency) {
            return 'This coupon is not valid for the selected currency.';
        }
        if ($plan && $this->plans()->exists() && ! $this->plans()->whereKey($plan->id)->exists()) {
            return 'This coupon does not apply to the selected plan.';
        }
        if ($user && $this->redemptions()->where('user_id', $user->id)->exists()) {
            return 'You have already used this coupon.';
        }

        return null;
    }

    public function discountFor(int $amount): int
    {
        return $this->type === 'percent'
            ? (int) round($amount * min(100, $this->value) / 100)
            : min($amount, $this->value);
    }
}
