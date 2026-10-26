<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    protected $fillable = ['interval', 'currency', 'amount', 'promo_amount', 'promo_ends_at', 'provider_price_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'promo_amount' => 'integer',
            'promo_ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasActivePromo(): bool
    {
        return $this->promo_amount !== null && ($this->promo_ends_at === null || $this->promo_ends_at->isFuture());
    }

    public function effectiveAmount(): int
    {
        return $this->hasActivePromo() ? (int) $this->promo_amount : $this->amount;
    }
}
