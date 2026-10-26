<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Subscription */
class SubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'plan' => $this->whenLoaded('plan', fn () => ['id' => $this->plan->id, 'name' => $this->plan->name, 'slug' => $this->plan->slug]),
            'price' => $this->whenLoaded('price', fn () => $this->price ? PlanResource::price($this->price) : null),
            'provider' => $this->provider,
            'isManual' => $this->is_manual,
            'currentPeriodStart' => $this->current_period_start?->toIso8601String(),
            'currentPeriodEnd' => $this->current_period_end?->toIso8601String(),
            'trialEndsAt' => $this->trial_ends_at?->toIso8601String(),
            'cancelAtPeriodEnd' => $this->cancel_at_period_end,
            'canceledAt' => $this->canceled_at?->toIso8601String(),
            'endsAt' => $this->ends_at?->toIso8601String(),
            'graceEndsAt' => $this->grace_ends_at?->toIso8601String(),
            'cancellationReason' => $this->when($request->is('api/v1/admin/*'), $this->cancellation_reason),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email]),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn (SubscriptionEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'fromStatus' => $e->from_status,
                'toStatus' => $e->to_status,
                'reason' => $e->reason,
                'actor' => $e->actor?->name,
                'createdAt' => $e->created_at?->toIso8601String(),
            ])),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
