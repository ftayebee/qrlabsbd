<?php

namespace Tests\Fakes;

use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Gateway\CheckoutResult;
use App\Services\Billing\Gateway\CheckoutSession;
use App\Services\Billing\Gateway\ProviderRefund;
use App\Services\Billing\Gateway\ProviderSubscription;
use App\Services\Billing\Gateway\StripeGateway;
use Carbon\CarbonImmutable;

/**
 * Keeps Stripe's real webhook signature verification (inherited) but replaces
 * every outbound API call with in-memory state the test controls.
 */
class FakeStripeGateway extends StripeGateway
{
    /** @var array<string, ProviderSubscription> */
    public array $subscriptions = [];

    /** @var array<string, CheckoutResult> */
    public array $checkouts = [];

    public int $fetches = 0;

    public function createCheckout(User $user, Subscription $pending, PlanPrice $price, ?Coupon $coupon, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $id = 'cs_test_'.$pending->id;
        $this->checkouts[$id] = new CheckoutResult($id, false, null, 'cus_'.$user->id);

        return new CheckoutSession($id, 'https://checkout.stripe.test/'.$id);
    }

    public function fetchCheckout(string $checkoutId): ?CheckoutResult
    {
        return $this->checkouts[$checkoutId] ?? null;
    }

    public function fetchSubscription(string $providerSubscriptionId): ProviderSubscription
    {
        $this->fetches++;

        return $this->subscriptions[$providerSubscriptionId];
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd): ProviderSubscription
    {
        $current = $this->subscriptions[$subscription->provider_subscription_id];

        return $this->subscriptions[$current->id] = $atPeriodEnd
            ? $this->state($current->id, $current->status, $current->metadata, cancelAtPeriodEnd: true)
            : $this->state($current->id, SubscriptionStatus::Canceled, $current->metadata, endedAt: CarbonImmutable::now());
    }

    public function resume(Subscription $subscription): ProviderSubscription
    {
        $current = $this->subscriptions[$subscription->provider_subscription_id];

        return $this->subscriptions[$current->id] = $this->state($current->id, $current->status, $current->metadata);
    }

    public function changePrice(Subscription $subscription, PlanPrice $price): ProviderSubscription
    {
        $current = $this->subscriptions[$subscription->provider_subscription_id];

        return $this->subscriptions[$current->id] = $this->state($current->id, $current->status, ['plan_price_id' => (string) $price->id] + $current->metadata);
    }

    public function billingPortalUrl(User $user, string $returnUrl): ?string
    {
        return 'https://billing.stripe.test/portal';
    }

    public function refund(Payment $payment, int $amount, ?string $reason): ProviderRefund
    {
        return new ProviderRefund('re_'.$payment->id.'_'.$amount, $payment->provider_payment_id, $amount, $payment->currency, 'succeeded', $reason);
    }

    /** Simulates the customer completing checkout at the provider. */
    public function completeCheckout(string $checkoutId, Subscription $local, PlanPrice $price, SubscriptionStatus $status = SubscriptionStatus::Active): string
    {
        $subId = 'sub_'.$local->id;
        $this->checkouts[$checkoutId] = new CheckoutResult($checkoutId, true, $subId, 'cus_'.$local->user_id);
        $this->subscriptions[$subId] = $this->state($subId, $status, ['local_subscription_id' => (string) $local->id, 'plan_price_id' => (string) $price->id]);

        return $subId;
    }

    /** @param array<string, string> $metadata */
    public function state(string $id, SubscriptionStatus $status, array $metadata, bool $cancelAtPeriodEnd = false, ?CarbonImmutable $endedAt = null): ProviderSubscription
    {
        $start = CarbonImmutable::now()->startOfDay();

        return new ProviderSubscription($id, $status, 'cus_test', $start, $start->addMonth(), null, $cancelAtPeriodEnd, $endedAt, $endedAt, $metadata);
    }
}
