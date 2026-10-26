<?php

namespace App\Services\Billing\Webhooks;

use App\Models\Subscription;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\Gateway\ProviderRefund;
use App\Services\Billing\Gateway\StripeGateway;
use App\Services\Billing\SubscriptionService;

/**
 * Maps Stripe event types onto the gateway-agnostic SubscriptionService.
 * Subscription state is always re-fetched from the API rather than taken from
 * the payload, which makes out-of-order delivery harmless.
 */
class StripeWebhookProcessor
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentGateway $gateway,
    ) {}

    /** @param array<string, mixed> $object */
    public function handle(string $type, array $object): bool
    {
        switch ($type) {
            case 'checkout.session.completed':
                $local = Subscription::query()->where('provider_checkout_id', $object['id'])->first();
                if ($local && ! empty($object['subscription'])) {
                    $this->subscriptions->applyProviderState($this->gateway->fetchSubscription((string) $object['subscription']), $local);
                }

                return true;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
            case 'customer.subscription.trial_will_end':
                if (! Subscription::query()->where('provider_subscription_id', $object['id'])->exists() && empty($object['metadata']['local_subscription_id'])) {
                    return false;
                }
                $this->subscriptions->applyProviderState($this->gateway->fetchSubscription((string) $object['id']));

                return true;

            case 'invoice.paid':
            case 'invoice.payment_succeeded':
            case 'invoice.payment_failed':
            case 'invoice.finalized':
            case 'invoice.voided':
            case 'invoice.marked_uncollectible':
                $invoice = StripeGateway::mapInvoice($object);
                $this->subscriptions->recordInvoice($invoice);
                if ($invoice->subscriptionId && ($sub = Subscription::query()->where('provider_subscription_id', $invoice->subscriptionId)->first())) {
                    // Payment outcomes move the subscription between active and past_due.
                    $this->subscriptions->sync($sub);
                }

                return true;

            case 'charge.refunded':
                foreach ($object['refunds']['data'] ?? [] as $r) {
                    $this->subscriptions->recordRefund(new ProviderRefund(
                        (string) $r['id'],
                        (string) ($object['payment_intent'] ?? $object['id']),
                        (int) $r['amount'],
                        strtoupper((string) $r['currency']),
                        (string) $r['status'],
                        $r['reason'] ?? null,
                    ));
                }

                return true;

            case 'refund.created':
            case 'refund.updated':
                $this->subscriptions->recordRefund(new ProviderRefund(
                    (string) $object['id'],
                    (string) ($object['payment_intent'] ?? $object['charge']),
                    (int) $object['amount'],
                    strtoupper((string) $object['currency']),
                    (string) $object['status'],
                    $object['reason'] ?? null,
                ));

                return true;
        }

        return false;
    }
}
