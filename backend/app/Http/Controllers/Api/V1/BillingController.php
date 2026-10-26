<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->subscriptions->currentPaid($user)?->load(['plan', 'price']);
        $history = $user->subscriptions()->with(['plan', 'price'])->where('status', '!=', 'incomplete')->latest('id')->limit(20)->get();

        return response()->json(['data' => [
            'current' => $current ? new SubscriptionResource($current) : null,
            'history' => SubscriptionResource::collection($history),
        ]]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_id' => ['required', 'integer', 'exists:plan_prices,id'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email address before subscribing.');

        $price = PlanPrice::query()->with('plan')->findOrFail($data['price_id']);
        $base = rtrim((string) config('app.frontend_url'), '/');
        $session = $this->subscriptions->checkout(
            $request->user(), $price, $data['coupon'] ?? null,
            $base.'/checkout/success', $base.'/pricing?checkout=cancelled',
        );

        return response()->json(['data' => ['url' => $session->url]]);
    }

    /**
     * Polled by the success page. Returns the state the server verified with the
     * provider; the browser redirect itself grants nothing.
     */
    public function checkoutStatus(Request $request, string $sessionId): JsonResponse
    {
        $pending = Subscription::query()->where('user_id', $request->user()->id)->where('provider_checkout_id', $sessionId)->firstOrFail();
        $sub = $this->subscriptions->reconcileCheckout($pending)->load(['plan', 'price']);

        return response()->json(['data' => [
            'status' => $sub->status->value,
            'active' => $sub->grantsAccess(),
            'subscription' => new SubscriptionResource($sub),
        ]]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $data = $request->validate(['price_id' => ['required', 'integer', 'exists:plan_prices,id']]);
        $current = $this->subscriptions->currentPaid($request->user()) ?? throw ValidationException::withMessages(['price_id' => 'You do not have an active subscription.']);
        $price = PlanPrice::query()->with('plan')->findOrFail($data['price_id']);

        if (Plan::query()->whereKey($price->plan_id)->value('is_default')) {
            // Downgrading to free means cancelling at period end.
            $sub = $this->subscriptions->cancel($current, $request->user(), true, 'Downgrade to free plan');
        } else {
            $sub = $this->subscriptions->changePlan($current, $price, $request->user());
        }

        return response()->json(['data' => new SubscriptionResource($sub->load(['plan', 'price']))]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255'], 'immediately' => ['boolean']]);
        $current = $this->subscriptions->currentPaid($request->user()) ?? abort(404, 'No active subscription.');
        $sub = $this->subscriptions->cancel($current, $request->user(), ! ($data['immediately'] ?? false), $data['reason'] ?? null);

        return response()->json(['data' => new SubscriptionResource($sub->load(['plan', 'price']))]);
    }

    public function resume(Request $request): JsonResponse
    {
        $current = $this->subscriptions->currentPaid($request->user()) ?? abort(404, 'No active subscription.');
        abort_unless($current->cancel_at_period_end, 422, 'This subscription is not scheduled to cancel.');

        return response()->json(['data' => new SubscriptionResource($this->subscriptions->resume($current, $request->user())->load(['plan', 'price']))]);
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($request->user()->invoices()->latest('issued_at')->paginate(20));
    }

    public function portal(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $url = $gateway->billingPortalUrl($request->user(), rtrim((string) config('app.frontend_url'), '/').'/dashboard/billing');
        abort_unless($url, 404, 'No billing account yet.');

        return response()->json(['data' => ['url' => $url]]);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40'], 'price_id' => ['required', 'integer', 'exists:plan_prices,id']]);
        $price = PlanPrice::query()->with('plan')->findOrFail($data['price_id']);
        $coupon = $this->subscriptions->validCoupon($data['code'], $price->plan, $request->user(), $price->currency);
        $discount = $coupon->discountFor($price->effectiveAmount());

        return response()->json(['data' => [
            'code' => $coupon->code,
            'name' => $coupon->name,
            'discount' => $discount,
            'total' => $price->effectiveAmount() - $discount,
            'currency' => $price->currency,
            'duration' => $coupon->duration,
            'durationMonths' => $coupon->duration_months,
        ]]);
    }
}
