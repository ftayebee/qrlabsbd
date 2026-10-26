<?php

namespace App\Services\Billing\Gateway;

use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;

/**
 * Stripe Billing implementation. Chosen first because it supports recurring
 * subscriptions in many currencies and countries; confirm your business
 * entity's country is supported before going live.
 *
 * Prices live in our database. Matching Stripe Prices are created lazily and
 * found again through deterministic lookup keys, so admins never need to touch
 * the Stripe dashboard to change pricing.
 */
class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function name(): string
    {
        return 'stripe';
    }

    private function stripe(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new GatewayException('Payments are not configured yet. Please contact support.');
        }

        return $this->client ??= new StripeClient(['api_key' => $secret]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function call(callable $call, string $action): mixed
    {
        try {
            return $call();
        } catch (ApiErrorException $e) {
            Log::warning('Stripe API error', ['action' => $action, 'type' => $e->getStripeCode(), 'status' => $e->getHttpStatus()]);
            throw new GatewayException('The payment provider could not complete this request. Please try again.');
        }
    }

    public function createCheckout(User $user, Subscription $pending, PlanPrice $price, ?Coupon $coupon, string $successUrl, string $cancelUrl): CheckoutSession
    {
        return $this->call(function () use ($user, $pending, $price, $coupon, $successUrl, $cancelUrl) {
            $metadata = ['local_subscription_id' => (string) $pending->id, 'plan_id' => (string) $price->plan_id, 'plan_price_id' => (string) $price->id, 'user_id' => (string) $user->id];
            $params = [
                'mode' => 'subscription',
                'customer' => $this->customerId($user),
                'client_reference_id' => (string) $pending->id,
                'line_items' => [['price' => $this->priceId($price), 'quantity' => 1]],
                'success_url' => $successUrl.(str_contains($successUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'subscription_data' => ['metadata' => $metadata],
                'metadata' => $metadata,
            ];
            $trialDays = $price->plan->trial_days;
            if ($trialDays > 0 && ! $user->subscriptions()->whereNotNull('trial_ends_at')->exists()) {
                $params['subscription_data']['trial_period_days'] = $trialDays;
            }
            if ($coupon) {
                $params['discounts'] = [['coupon' => $this->couponId($coupon)]];
            } else {
                $params['allow_promotion_codes'] = false;
            }
            $session = $this->stripe()->checkout->sessions->create($params, ['idempotency_key' => 'checkout-'.$pending->id]);

            return new CheckoutSession($session->id, (string) $session->url);
        }, 'checkout.create');
    }

    public function fetchCheckout(string $checkoutId): ?CheckoutResult
    {
        return $this->call(function () use ($checkoutId) {
            $s = $this->stripe()->checkout->sessions->retrieve($checkoutId);

            return new CheckoutResult($s->id, $s->status === 'complete', is_string($s->subscription) ? $s->subscription : $s->subscription?->id, is_string($s->customer) ? $s->customer : $s->customer?->id);
        }, 'checkout.retrieve');
    }

    public function fetchSubscription(string $providerSubscriptionId): ProviderSubscription
    {
        return $this->call(fn () => $this->mapSubscription($this->stripe()->subscriptions->retrieve($providerSubscriptionId)), 'subscription.retrieve');
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd): ProviderSubscription
    {
        return $this->call(function () use ($subscription, $atPeriodEnd) {
            $id = (string) $subscription->provider_subscription_id;
            $s = $atPeriodEnd
                ? $this->stripe()->subscriptions->update($id, ['cancel_at_period_end' => true])
                : $this->stripe()->subscriptions->cancel($id, ['prorate' => false]);

            return $this->mapSubscription($s);
        }, 'subscription.cancel');
    }

    public function resume(Subscription $subscription): ProviderSubscription
    {
        return $this->call(fn () => $this->mapSubscription(
            $this->stripe()->subscriptions->update((string) $subscription->provider_subscription_id, ['cancel_at_period_end' => false])
        ), 'subscription.resume');
    }

    public function changePrice(Subscription $subscription, PlanPrice $price): ProviderSubscription
    {
        return $this->call(function () use ($subscription, $price) {
            $id = (string) $subscription->provider_subscription_id;
            $current = $this->stripe()->subscriptions->retrieve($id);
            $itemId = $current->items->data[0]->id;
            $s = $this->stripe()->subscriptions->update($id, [
                'items' => [['id' => $itemId, 'price' => $this->priceId($price)]],
                'proration_behavior' => 'create_prorations',
                'cancel_at_period_end' => false,
                'metadata' => ['plan_id' => (string) $price->plan_id, 'plan_price_id' => (string) $price->id, 'local_subscription_id' => (string) $subscription->id, 'user_id' => (string) $subscription->user_id],
            ]);

            return $this->mapSubscription($s);
        }, 'subscription.change');
    }

    public function billingPortalUrl(User $user, string $returnUrl): ?string
    {
        if (! $user->provider_customer_id) {
            return null;
        }

        return $this->call(fn () => $this->stripe()->billingPortal->sessions->create(['customer' => $user->provider_customer_id, 'return_url' => $returnUrl])->url, 'portal.create');
    }

    public function refund(Payment $payment, int $amount, ?string $reason): ProviderRefund
    {
        return $this->call(function () use ($payment, $amount) {
            $key = str_starts_with($payment->provider_payment_id, 'pi_') ? 'payment_intent' : 'charge';
            $r = $this->stripe()->refunds->create([$key => $payment->provider_payment_id, 'amount' => $amount, 'reason' => 'requested_by_customer']);

            return new ProviderRefund($r->id, $payment->provider_payment_id, (int) $r->amount, strtoupper($r->currency), (string) $r->status, null);
        }, 'refund.create');
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $raw = $request->getContent();
        try {
            if ($secret === '') {
                throw new SignatureVerificationException('Webhook secret not configured');
            }
            $event = Webhook::constructEvent($raw, (string) $request->header('Stripe-Signature'), $secret, (int) config('services.stripe.webhook_tolerance'));
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            throw new InvalidWebhookSignature('Invalid Stripe signature');
        }

        return new WebhookPayload($event->id, $event->type, $event->data->object->toArray(), $raw);
    }

    /** Converts a Stripe invoice (from a webhook) to our DTO. Handles both pre- and post-2025 API shapes. */
    /** @param array<string, mixed> $i */
    public static function mapInvoice(array $i): ProviderInvoice
    {
        $subscriptionId = $i['subscription'] ?? ($i['parent']['subscription_details']['subscription'] ?? null);
        $paymentId = $i['payment_intent'] ?? ($i['payments']['data'][0]['payment']['payment_intent'] ?? null) ?? $i['charge'] ?? null;
        $discount = array_sum(array_map(fn ($d) => (int) ($d['amount'] ?? 0), $i['total_discount_amounts'] ?? []));
        $tax = (int) ($i['tax'] ?? array_sum(array_map(fn ($t) => (int) ($t['amount'] ?? 0), $i['total_taxes'] ?? [])));

        return new ProviderInvoice(
            id: $i['id'],
            subscriptionId: is_array($subscriptionId) ? ($subscriptionId['id'] ?? null) : $subscriptionId,
            customerId: is_array($i['customer'] ?? null) ? $i['customer']['id'] : ($i['customer'] ?? null),
            number: $i['number'] ?? null,
            status: (string) ($i['status'] ?? 'open'),
            currency: strtoupper((string) $i['currency']),
            subtotal: (int) ($i['subtotal'] ?? 0),
            discount: $discount,
            tax: $tax,
            total: (int) ($i['total'] ?? 0),
            periodStart: self::ts($i['lines']['data'][0]['period']['start'] ?? $i['period_start'] ?? null),
            periodEnd: self::ts($i['lines']['data'][0]['period']['end'] ?? $i['period_end'] ?? null),
            issuedAt: self::ts($i['created'] ?? null),
            paidAt: self::ts($i['status_transitions']['paid_at'] ?? null),
            hostedUrl: $i['hosted_invoice_url'] ?? null,
            pdfUrl: $i['invoice_pdf'] ?? null,
            paymentId: is_array($paymentId) ? ($paymentId['id'] ?? null) : $paymentId,
            attempt: (int) ($i['attempt_count'] ?? 1),
            failureReason: $i['last_finalization_error']['message'] ?? null,
        );
    }

    private function mapSubscription(StripeObject $s): ProviderSubscription
    {
        $item = $s->items->data[0] ?? null;

        return new ProviderSubscription(
            id: $s->id,
            status: match ($s->status) {
                'trialing' => SubscriptionStatus::Trialing,
                'active' => SubscriptionStatus::Active,
                'past_due' => SubscriptionStatus::PastDue,
                'canceled' => SubscriptionStatus::Canceled,
                'incomplete' => SubscriptionStatus::Incomplete,
                // Retries exhausted (unpaid), abandoned (incomplete_expired) or paused: no access.
                default => SubscriptionStatus::Expired,
            },
            customerId: is_string($s->customer) ? $s->customer : $s->customer?->id,
            currentPeriodStart: self::ts($s->current_period_start ?? $item?->current_period_start),
            currentPeriodEnd: self::ts($s->current_period_end ?? $item?->current_period_end),
            trialEnd: self::ts($s->trial_end),
            cancelAtPeriodEnd: (bool) $s->cancel_at_period_end || $s->cancel_at !== null,
            canceledAt: self::ts($s->canceled_at),
            endedAt: self::ts($s->ended_at),
            metadata: $s->metadata?->toArray() ?? [],
        );
    }

    private function customerId(User $user): string
    {
        if ($user->provider_customer_id) {
            return $user->provider_customer_id;
        }
        $customer = $this->stripe()->customers->create(['email' => $user->email, 'name' => $user->name, 'metadata' => ['user_id' => (string) $user->id]], ['idempotency_key' => 'customer-'.$user->id]);
        $user->forceFill(['provider_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /** Finds or creates the Stripe Price for the amount a new subscriber pays today. */
    private function priceId(PlanPrice $price): string
    {
        $amount = $price->effectiveAmount();
        $lookup = sprintf('qrio_pp%d_%s_%s_%d', $price->id, strtolower($price->currency), $price->interval, $amount);
        $existing = $this->stripe()->prices->all(['lookup_keys' => [$lookup], 'limit' => 1])->data[0] ?? null;
        if ($existing) {
            return $existing->id;
        }
        $created = $this->stripe()->prices->create([
            'currency' => strtolower($price->currency),
            'unit_amount' => $amount,
            'recurring' => ['interval' => $price->interval],
            'lookup_key' => $lookup,
            'product_data' => ['name' => config('app.name').' '.$price->plan->name, 'metadata' => ['plan_id' => (string) $price->plan_id]],
            'metadata' => ['plan_price_id' => (string) $price->id],
        ]);
        $price->forceFill(['provider_price_id' => $created->id])->save();

        return $created->id;
    }

    private function couponId(Coupon $coupon): string
    {
        if ($coupon->provider_coupon_id) {
            return $coupon->provider_coupon_id;
        }
        $params = ['name' => $coupon->name, 'duration' => $coupon->duration, 'metadata' => ['coupon_id' => (string) $coupon->id]];
        if ($coupon->type === 'percent') {
            $params['percent_off'] = min(100, $coupon->value);
        } else {
            $params['amount_off'] = $coupon->value;
            $params['currency'] = strtolower((string) $coupon->currency);
        }
        if ($coupon->duration === 'repeating') {
            $params['duration_in_months'] = $coupon->duration_months ?? 1;
        }
        $created = $this->stripe()->coupons->create($params);
        $coupon->forceFill(['provider_coupon_id' => $created->id])->save();

        return $created->id;
    }

    private static function ts(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) && $value > 0 ? CarbonImmutable::createFromTimestampUTC((int) $value) : null;
    }
}
