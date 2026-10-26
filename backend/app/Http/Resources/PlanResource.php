<?php

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $admin = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'isCustom' => $this->is_custom,
            'highlight' => $this->highlight,
            'trialDays' => $this->trial_days,
            'sortOrder' => $this->sort_order,
            'ctaLabel' => $this->cta_label,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices
                ->when(! $admin, fn ($c) => $c->where('is_active', true))
                ->map(fn (PlanPrice $p) => self::price($p, $admin))->values()),
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn (PlanFeature $f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'value' => $f->value,
                'highlighted' => $f->is_highlighted,
                'sortOrder' => $f->sort_order,
            ])->values()),
            $this->mergeWhen($admin, fn () => [
                'status' => $this->status->value,
                'isPublic' => $this->is_public,
                'isDefault' => $this->is_default,
                'subscribersCount' => $this->subscriptions_count ?? null,
                'createdAt' => $this->created_at?->toIso8601String(),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public static function price(PlanPrice $p, bool $admin = false): array
    {
        $showPromo = $admin || $p->hasActivePromo();

        return [
            'id' => $p->id,
            'interval' => $p->interval,
            'currency' => $p->currency,
            'amount' => $p->amount,
            'promoAmount' => $showPromo ? $p->promo_amount : null,
            'promoEndsAt' => $showPromo ? $p->promo_ends_at?->toIso8601String() : null,
            'effectiveAmount' => $p->effectiveAmount(),
        ] + ($admin ? ['isActive' => $p->is_active, 'providerPriceId' => $p->provider_price_id] : []);
    }
}
