<?php

namespace App\Services\Billing\Gateway;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Boundary between subscription business logic and a payment provider.
 * Implementations translate provider objects into the DTOs in this namespace;
 * nothing outside the gateway knows provider-specific field names.
 */
interface PaymentGateway
{
    public function name(): string;

    public function createCheckout(User $user, Subscription $pending, PlanPrice $price, ?Coupon $coupon, string $successUrl, string $cancelUrl): CheckoutSession;

    /** Server-side lookup used to reconcile a checkout without trusting the browser redirect. */
    public function fetchCheckout(string $checkoutId): ?CheckoutResult;

    public function fetchSubscription(string $providerSubscriptionId): ProviderSubscription;

    public function cancel(Subscription $subscription, bool $atPeriodEnd): ProviderSubscription;

    public function resume(Subscription $subscription): ProviderSubscription;

    public function changePrice(Subscription $subscription, PlanPrice $price): ProviderSubscription;

    public function billingPortalUrl(User $user, string $returnUrl): ?string;

    public function refund(Payment $payment, int $amount, ?string $reason): ProviderRefund;

    /**
     * Verifies the signature and returns the parsed event.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): WebhookPayload;
}
