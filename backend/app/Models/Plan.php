<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'status', 'is_public', 'is_default', 'is_custom', 'highlight', 'cta_label', 'trial_days', 'sort_order'];

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'is_public' => 'boolean',
            'is_default' => 'boolean',
            'is_custom' => 'boolean',
            'highlight' => 'boolean',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('sort_order');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @param Builder<Plan> $query */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('status', PlanStatus::Published)->where('is_public', true)->orderBy('sort_order');
    }

    public static function defaultPlan(): self
    {
        return static::query()->where('is_default', true)->firstOrFail();
    }
}
