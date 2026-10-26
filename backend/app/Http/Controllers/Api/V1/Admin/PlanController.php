<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\AuditLogger;
use App\Services\Billing\Entitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Plans, prices and entitlements are data, not code. Changing a price never
 * alters what existing subscribers pay: the gateway creates a new provider
 * price for the new amount and existing subscriptions keep theirs.
 */
class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::query()->with(['prices', 'features'])->withCount(['subscriptions' => fn ($q) => $q->whereIn('status', SubscriptionStatus::accessValues())])->orderBy('sort_order')->get());
    }

    public function show(Plan $plan): PlanResource
    {
        return new PlanResource($plan->load(['prices', 'features'])->loadCount(['subscriptions' => fn ($q) => $q->whereIn('status', SubscriptionStatus::accessValues())]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $plan = DB::transaction(fn () => $this->persist(new Plan, $data));
        $this->audit->log($request->user(), 'plan.created', $plan, null, ['slug' => $plan->slug]);

        return (new PlanResource($plan->load(['prices', 'features'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Plan $plan): PlanResource
    {
        $data = $this->validated($request, $plan);
        $before = (new PlanResource($plan->load(['prices', 'features'])))->resolve($request);
        $plan = DB::transaction(fn () => $this->persist($plan, $data));
        $this->audit->log($request->user(), 'plan.updated', $plan, $data['reason'] ?? null, ['before' => $before, 'after' => (new PlanResource($plan->load(['prices', 'features'])))->resolve($request)]);

        return new PlanResource($plan->load(['prices', 'features']));
    }

    /** Plans are archived, not deleted, so subscription history stays intact. */
    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        abort_if($plan->is_default, 422, 'The default free plan cannot be archived.');
        $plan->update(['status' => PlanStatus::Archived, 'is_public' => false]);
        Cache::forget('public:plans');
        $this->audit->log($request->user(), 'plan.archived', $plan, $request->input('reason'));

        return response()->json(['message' => 'Plan archived. Existing subscribers keep it until they change plan.']);
    }

    public function duplicate(Request $request, Plan $plan): JsonResponse
    {
        $copy = DB::transaction(function () use ($plan) {
            $copy = $plan->replicate(['is_default']);
            $copy->fill(['slug' => $plan->slug.'-copy-'.Str::lower(Str::random(4)), 'name' => $plan->name.' (copy)', 'status' => PlanStatus::Draft, 'is_default' => false])->save();
            foreach ($plan->prices as $price) {
                $copy->prices()->create($price->only(['interval', 'currency', 'amount', 'promo_amount', 'promo_ends_at', 'is_active']));
            }
            foreach ($plan->features as $feature) {
                $copy->features()->create($feature->only(['key', 'label', 'type', 'value', 'is_highlighted', 'sort_order']));
            }

            return $copy;
        });
        $this->audit->log($request->user(), 'plan.duplicated', $copy, null, ['from' => $plan->slug]);

        return (new PlanResource($copy->load(['prices', 'features'])))->response()->setStatusCode(201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:64', Rule::unique('plans')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(PlanStatus::class)],
            'is_public' => ['boolean'],
            'is_custom' => ['boolean'],
            'highlight' => ['boolean'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'trial_days' => ['integer', 'between:0,90'],
            'sort_order' => ['integer', 'between:0,1000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'prices' => ['array', 'max:20'],
            'prices.*.id' => ['nullable', 'integer'],
            'prices.*.interval' => ['required', Rule::in(['month', 'year'])],
            'prices.*.currency' => ['required', 'string', 'size:3', 'alpha'],
            'prices.*.amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'prices.*.promo_amount' => ['nullable', 'integer', 'min:0', 'lt:prices.*.amount'],
            'prices.*.promo_ends_at' => ['nullable', 'date'],
            'prices.*.is_active' => ['boolean'],
            'features' => ['array', 'max:50'],
            'features.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'features.*.label' => ['required', 'string', 'max:120'],
            'features.*.type' => ['required', Rule::in(['boolean', 'limit', 'text'])],
            'features.*.value' => ['required', 'string', 'max:120'],
            'features.*.is_highlighted' => ['boolean'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function persist(Plan $plan, array $data): Plan
    {
        $plan->fill(collect($data)->except(['prices', 'features', 'reason'])->all())->save();

        if (array_key_exists('prices', $data)) {
            $keep = [];
            foreach ($data['prices'] as $p) {
                $currency = strtoupper($p['currency']);
                $price = isset($p['id']) ? $plan->prices()->find($p['id']) : null;
                $price ??= $plan->prices()->firstOrNew(['interval' => $p['interval'], 'currency' => $currency]);
                $amountChanged = $price->exists && $price->amount !== (int) $p['amount'];
                $price->fill([
                    'interval' => $p['interval'], 'currency' => $currency, 'amount' => (int) $p['amount'],
                    'promo_amount' => $p['promo_amount'] ?? null, 'promo_ends_at' => $p['promo_ends_at'] ?? null,
                    'is_active' => $p['is_active'] ?? true,
                ]);
                if ($amountChanged) {
                    // The gateway will create a new provider price; existing subscribers are unaffected.
                    $price->provider_price_id = null;
                }
                $price->save();
                $keep[] = $price->id;
            }
            // Prices referenced by subscriptions are deactivated rather than deleted.
            $plan->prices()->whereNotIn('id', $keep)->get()->each(fn (PlanPrice $old) => $old->subscriptions()->exists() ? $old->update(['is_active' => false]) : $old->delete());
        }

        if (array_key_exists('features', $data)) {
            $plan->features()->delete();
            foreach (array_values($data['features']) as $i => $f) {
                if (isset(Entitlements::DEFAULTS[$f['key']]) && $f['type'] === 'limit' && ! is_numeric($f['value'])) {
                    abort(422, "Feature {$f['key']} must be a number (-1 for unlimited).");
                }
                $plan->features()->create($f + ['sort_order' => $i, 'is_highlighted' => $f['is_highlighted'] ?? false]);
            }
        }

        if ($plan->is_default) {
            Plan::query()->whereKeyNot($plan->id)->update(['is_default' => false]);
        }
        Cache::forget('public:plans');

        return $plan;
    }
}
