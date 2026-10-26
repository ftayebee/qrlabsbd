<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'plan_price_id', 'coupon_id', 'status', 'provider', 'provider_subscription_id', 'provider_checkout_id',
        'trial_ends_at', 'current_period_start', 'current_period_end', 'cancel_at_period_end', 'canceled_at', 'ends_at',
        'grace_ends_at', 'cancellation_reason', 'is_manual',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->latest('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
