<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions, private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['incomplete', 'trialing', 'active', 'past_due', 'canceled', 'expired'])],
            'plan_id' => ['nullable', 'integer'],
            'renewing_before' => ['nullable', 'date'],
        ]);

        $subs = Subscription::query()
            ->with(['plan', 'price', 'user:id,name,email'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'incomplete'))
            ->when($f['plan_id'] ?? null, fn ($q, $p) => $q->where('plan_id', $p))
            ->when($f['renewing_before'] ?? null, fn ($q, $d) => $q->where('current_period_end', '<=', $d))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))
                ->orWhere('provider_subscription_id', $s)))
            ->latest('id')->paginate(25)->withQueryString();

        return SubscriptionResource::collection($subs);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription->load(['plan', 'price', 'user:id,name,email', 'events.actor:id,name']));
    }

    public function grant(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $sub = $this->subscriptions->grantManual(
            $user, Plan::query()->findOrFail($data['plan_id']),
            isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            $request->user(), $data['reason'],
        );

        return (new SubscriptionResource($sub->load(['plan', 'price'])))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validate(['immediately' => ['boolean'], 'reason' => ['required', 'string', 'max:255']]);
        $sub = $this->subscriptions->cancel($subscription, $request->user(), ! ($data['immediately'] ?? false), $data['reason']);
        $this->audit->log($request->user(), 'subscription.admin_cancel', $sub, $data['reason'], ['immediately' => (bool) ($data['immediately'] ?? false)]);

        return new SubscriptionResource($sub->load(['plan', 'price', 'events.actor:id,name']));
    }

    public function extendTrial(Request $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validate(['until' => ['required', 'date', 'after:now'], 'reason' => ['required', 'string', 'max:255']]);
        abort_unless($subscription->is_manual || $subscription->status->value === 'trialing', 422, 'Only trialing or manually granted subscriptions can be extended here. Use the payment provider for paid subscriptions.');

        return new SubscriptionResource($this->subscriptions->extendTrial($subscription, CarbonImmutable::parse($data['until']), $request->user(), $data['reason'])->load(['plan', 'price', 'events.actor:id,name']));
    }

    /** Re-reads state from the provider; used to reconcile if a webhook was missed. */
    public function sync(Request $request, Subscription $subscription): SubscriptionResource
    {
        abort_if($subscription->is_manual || ! $subscription->provider_subscription_id, 422, 'Nothing to sync for this subscription.');
        $sub = $this->subscriptions->sync($subscription);
        $this->audit->log($request->user(), 'subscription.sync', $sub);

        return new SubscriptionResource($sub->load(['plan', 'price', 'events.actor:id,name']));
    }
}
