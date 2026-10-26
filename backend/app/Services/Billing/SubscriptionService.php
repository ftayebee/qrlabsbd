<?php

namespace App\Services\Billing;

use App\Enums\PaymentStatus;
use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Services\AuditLogger;
use App\Services\Billing\Gateway\CheckoutSession;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\Gateway\ProviderInvoice;
use App\Services\Billing\Gateway\ProviderRefund;
use App\Services\Billing\Gateway\ProviderSubscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gateway-agnostic subscription lifecycle. Paid access is only ever granted
 * from state the server obtained from the provider (webhook or API call),
 * never from a browser redirect.
 */
class SubscriptionService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EntitlementService $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function checkout(User $user, PlanPrice $price, ?string $couponCode, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $plan = $price->plan;
        if ($plan->status !== PlanStatus::Published || ! $price->is_active || $plan->is_custom || $price->effectiveAmount() === 0) {
            throw ValidationException::withMessages(['price_id' => 'This plan is not available for online checkout.']);
        }
        $current = $this->currentPaid($user);
        if ($current && ! $current->is_manual) {
            throw ValidationException::withMessages(['price_id' => 'You already have a subscription. Change plan from the billing page instead.']);
        }

        $coupon = $couponCode ? $this->validCoupon($couponCode, $plan, $user, $price->currency) : null;

        $pending = DB::transaction(function () use ($user, $plan, $price, $coupon) {
            // Abandon earlier unfinished checkouts so only one can complete.
            Subscription::query()->where('user_id', $user->id)->where('status', SubscriptionStatus::Incomplete)
                ->whereNull('provider_subscription_id')->update(['status' => SubscriptionStatus::Expired]);

            return Subscription::query()->create([
                'user_id' => $user->id, 'plan_id' => $plan->id, 'plan_price_id' => $price->id, 'coupon_id' => $coupon?->id,
                'status' => SubscriptionStatus::Incomplete, 'provider' => $this->gateway->name(),
            ]);
        });

        $session = $this->gateway->createCheckout($user, $pending, $price, $coupon, $successUrl, $cancelUrl);
        $pending->update(['provider_checkout_id' => $session->id]);
        $this->event($pending, 'checkout_started', null, SubscriptionStatus::Incomplete, $user);

        return $session;
    }

    /** Server-side reconciliation of a checkout (used by the success page and the webhook). */
    public function reconcileCheckout(Subscription $pending): Subscription
    {
        if ($pending->provider_subscription_id || ! $pending->provider_checkout_id) {
            return $pending->provider_subscription_id ? $this->sync($pending) : $pending;
        }
        $result = $this->gateway->fetchCheckout($pending->provider_checkout_id);
        if (! $result?->complete || ! $result->providerSubscriptionId) {
            return $pending;
        }

        return $this->applyProviderState($this->gateway->fetchSubscription($result->providerSubscriptionId), $pending);
    }

    public function sync(Subscription $subscription): Subscription
    {
        return $this->applyProviderState($this->gateway->fetchSubscription((string) $subscription->provider_subscription_id), $subscription);
    }

    /** Applies provider-reported state under a row lock. Idempotent: replaying the same state changes nothing. */
    public function applyProviderState(ProviderSubscription $state, ?Subscription $hint = null): Subscription
    {
        return DB::transaction(function () use ($state, $hint) {
            $sub = Subscription::query()->where('provider_subscription_id', $state->id)->lockForUpdate()->first();
            if (! $sub) {
                $localId = $hint?->id ?? ($state->metadata['local_subscription_id'] ?? null);
                $sub = $localId ? Subscription::query()->whereKey($localId)->lockForUpdate()->first() : null;
            }
            if (! $sub) {
                throw new \RuntimeException('No local subscription for provider subscription '.$state->id);
            }

            $from = $sub->status;
            $priceId = isset($state->metadata['plan_price_id']) ? (int) $state->metadata['plan_price_id'] : $sub->plan_price_id;
            $price = $priceId ? PlanPrice::query()->find($priceId) : null;

            $sub->fill([
                'provider_subscription_id' => $state->id,
                'status' => $state->status,
                'current_period_start' => $state->currentPeriodStart,
                'current_period_end' => $state->currentPeriodEnd,
                'trial_ends_at' => $state->trialEnd,
                'cancel_at_period_end' => $state->cancelAtPeriodEnd,
                'canceled_at' => $state->canceledAt,
                'ends_at' => $state->endedAt ?? ($state->cancelAtPeriodEnd ? $state->currentPeriodEnd : null),
            ]);
            if ($price) {
                $sub->plan_price_id = $price->id;
                $sub->plan_id = $price->plan_id;
            }
            if (in_array($state->status, [SubscriptionStatus::Canceled, SubscriptionStatus::Expired], true) && ! $sub->grace_ends_at) {
                $sub->grace_ends_at = $this->graceFrom($state->endedAt ?? now());
            }
            if ($state->status->grantsAccess()) {
                $sub->grace_ends_at = null;
            }
            $sub->save();

            if ($state->customerId) {
                User::query()->whereKey($sub->user_id)->whereNull('provider_customer_id')->update(['provider_customer_id' => $state->customerId]);
            }

            if ($from !== $sub->status) {
                $this->event($sub, 'status_changed', $from, $sub->status);
                if ($sub->status->grantsAccess()) {
                    $this->supersedeOthers($sub);
                    $this->redeemCoupon($sub);
                }
            }
            if ($sub->wasChanged('plan_id')) {
                $this->event($sub, 'plan_changed', $sub->status, $sub->status, null, null, ['from_plan_id' => $sub->getOriginal('plan_id'), 'to_plan_id' => $sub->plan_id]);
            }

            $this->entitlements->forget($sub->user);

            return $sub;
        });
    }

    public function recordInvoice(ProviderInvoice $inv): ?Invoice
    {
        return DB::transaction(function () use ($inv) {
            $sub = $inv->subscriptionId ? Subscription::query()->where('provider_subscription_id', $inv->subscriptionId)->first() : null;
            $userId = $sub?->user_id ?? User::query()->where('provider_customer_id', $inv->customerId)->value('id');
            if (! $userId) {
                return null;
            }

            $invoice = Invoice::query()->lockForUpdate()->firstOrNew(['provider' => $this->gateway->name(), 'provider_invoice_id' => $inv->id]);
            $invoice->fill([
                'user_id' => $userId,
                'subscription_id' => $sub?->id,
                'number' => $invoice->number ?? ($inv->number ?: 'QR-'.strtoupper(substr(sha1($inv->id), 0, 10))),
                'status' => $inv->status,
                'currency' => $inv->currency,
                'subtotal' => $inv->subtotal,
                'discount' => $inv->discount,
                'tax' => $inv->tax,
                'total' => $inv->total,
                'period_start' => $inv->periodStart,
                'period_end' => $inv->periodEnd,
                'issued_at' => $inv->issuedAt,
                'paid_at' => $inv->paidAt,
                'hosted_url' => $inv->hostedUrl,
                'pdf_url' => $inv->pdfUrl,
            ])->save();

            if ($inv->paymentId && $inv->total > 0) {
                $payment = Payment::query()->lockForUpdate()->firstOrNew(['provider_payment_id' => $inv->paymentId]);
                $alreadyFailed = $payment->exists && $payment->status === PaymentStatus::Failed && $payment->attempt === $inv->attempt;
                $status = $inv->status === 'paid' ? PaymentStatus::Succeeded : ($inv->status === 'open' && $inv->attempt > 0 ? PaymentStatus::Failed : PaymentStatus::Pending);
                $payment->fill([
                    'user_id' => $userId, 'subscription_id' => $sub?->id, 'invoice_id' => $invoice->id,
                    'provider' => $this->gateway->name(), 'currency' => $inv->currency, 'amount' => $inv->total,
                    'status' => $payment->status === PaymentStatus::Refunded || $payment->status === PaymentStatus::PartiallyRefunded ? $payment->status : $status,
                    'attempt' => $inv->attempt, 'paid_at' => $inv->paidAt, 'failure_reason' => $status === PaymentStatus::Failed ? ($inv->failureReason ?? 'Payment declined') : null,
                ])->save();

                if ($status === PaymentStatus::Failed && ! $alreadyFailed) {
                    User::query()->find($userId)?->notify(new PaymentFailedNotification($invoice));
                }
            }

            return $invoice;
        });
    }

    public function recordRefund(ProviderRefund $r): void
    {
        DB::transaction(function () use ($r) {
            $payment = Payment::query()->where('provider_payment_id', $r->paymentId)->lockForUpdate()->first();
            if (! $payment) {
                return;
            }
            Refund::query()->updateOrCreate(['provider_refund_id' => $r->id], [
                'payment_id' => $payment->id, 'amount' => $r->amount, 'currency' => $r->currency, 'status' => $r->status, 'reason' => $r->reason,
            ]);
            $refunded = (int) Refund::query()->where('payment_id', $payment->id)->where('status', 'succeeded')->sum('amount');
            $payment->update([
                'refunded_amount' => $refunded,
                'status' => $refunded >= $payment->amount ? PaymentStatus::Refunded : ($refunded > 0 ? PaymentStatus::PartiallyRefunded : $payment->status),
            ]);
        });
    }

    public function cancel(Subscription $sub, User $actor, bool $atPeriodEnd, ?string $reason): Subscription
    {
        if ($sub->is_manual) {
            return DB::transaction(function () use ($sub, $actor, $atPeriodEnd, $reason) {
                $from = $sub->status;
                $sub->fill($atPeriodEnd
                    ? ['cancel_at_period_end' => true, 'cancellation_reason' => $reason]
                    : ['status' => SubscriptionStatus::Canceled, 'canceled_at' => now(), 'ends_at' => now(), 'grace_ends_at' => $this->graceFrom(now()), 'cancellation_reason' => $reason]
                )->save();
                $this->event($sub, 'canceled', $from, $sub->status, $actor, $reason);
                $this->entitlements->forget($sub->user);

                return $sub;
            });
        }

        $sub = $this->applyProviderState($this->gateway->cancel($sub, $atPeriodEnd), $sub);
        $sub->update(['cancellation_reason' => $reason]);
        $this->event($sub, $atPeriodEnd ? 'cancel_scheduled' : 'canceled', $sub->status, $sub->status, $actor, $reason);

        return $sub;
    }

    public function resume(Subscription $sub, User $actor): Subscription
    {
        $sub = $sub->is_manual
            ? tap($sub)->update(['cancel_at_period_end' => false])
            : $this->applyProviderState($this->gateway->resume($sub), $sub);
        $this->event($sub, 'resumed', $sub->status, $sub->status, $actor);

        return $sub;
    }

    /** Upgrade or downgrade. The provider prorates; state is taken from its API response. */
    public function changePlan(Subscription $sub, PlanPrice $price, User $actor): Subscription
    {
        if ($price->plan->status !== PlanStatus::Published || ! $price->is_active || $price->plan->is_custom) {
            throw ValidationException::withMessages(['price_id' => 'This plan is not available.']);
        }
        if ($sub->is_manual || ! $sub->provider_subscription_id) {
            throw ValidationException::withMessages(['price_id' => 'This subscription is managed by our team. Contact support to change it.']);
        }
        if ($sub->price && $sub->price->currency !== $price->currency) {
            throw ValidationException::withMessages(['price_id' => 'Plan changes must keep the same currency.']);
        }

        $updated = $this->applyProviderState($this->gateway->changePrice($sub, $price), $sub);
        $this->event($updated, 'plan_change_requested', $updated->status, $updated->status, $actor, null, ['plan_price_id' => $price->id]);

        return $updated;
    }

    /** Admin override: grant a plan without payment (e.g. custom/enterprise deals). Always audited. */
    public function grantManual(User $user, Plan $plan, ?CarbonInterface $endsAt, User $actor, string $reason): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $endsAt, $actor, $reason) {
            $sub = Subscription::query()->create([
                'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'provider' => 'manual',
                'is_manual' => true, 'current_period_start' => now(), 'current_period_end' => $endsAt, 'ends_at' => $endsAt,
            ]);
            $this->event($sub, 'manual_grant', null, SubscriptionStatus::Active, $actor, $reason, ['ends_at' => $endsAt?->toIso8601String()]);
            $this->supersedeOthers($sub);
            $this->audit->log($actor, 'subscription.manual_grant', $sub, $reason, ['plan' => $plan->slug, 'ends_at' => $endsAt?->toIso8601String()]);
            $this->entitlements->forget($user);

            return $sub;
        });
    }

    public function extendTrial(Subscription $sub, CarbonInterface $until, User $actor, string $reason): Subscription
    {
        $old = $sub->trial_ends_at;
        $sub->update(['trial_ends_at' => $until]);
        $this->event($sub, 'trial_extended', $sub->status, $sub->status, $actor, $reason, ['from' => $old?->toIso8601String(), 'to' => $until->toIso8601String()]);
        $this->audit->log($actor, 'subscription.trial_extended', $sub, $reason, ['to' => $until->toIso8601String()]);

        return $sub;
    }

    /** Scheduler: ends manual subscriptions whose term is over. Provider subscriptions are ended by webhooks. */
    public function expireManual(): int
    {
        $count = 0;
        Subscription::query()->where('is_manual', true)->whereIn('status', SubscriptionStatus::accessValues())
            ->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->each(function (Subscription $sub) use (&$count) {
                $from = $sub->status;
                $sub->update(['status' => SubscriptionStatus::Expired, 'grace_ends_at' => $this->graceFrom($sub->ends_at ?? now())]);
                $this->event($sub, 'expired', $from, SubscriptionStatus::Expired);
                $count++;
            });

        return $count;
    }

    public function currentPaid(User $user): ?Subscription
    {
        return Subscription::query()->where('user_id', $user->id)->whereIn('status', SubscriptionStatus::accessValues())->latest('id')->first();
    }

    public function validCoupon(string $code, Plan $plan, User $user, string $currency): Coupon
    {
        $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();
        $reason = $coupon ? $coupon->unusableReason($plan, $user, $currency) : 'This coupon code is not valid.';
        if ($reason) {
            throw ValidationException::withMessages(['coupon' => $reason]);
        }

        return $coupon;
    }

    private function graceFrom(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays((int) config('qrio.billing.grace_days'));
    }

    /**
     * Ends earlier manual grants when a newer subscription activates. Paid
     * provider subscriptions are never ended implicitly; they must be cancelled
     * through the gateway so billing stops too.
     */
    private function supersedeOthers(Subscription $current): void
    {
        Subscription::query()->where('user_id', $current->user_id)->whereKeyNot($current->id)
            ->whereIn('status', SubscriptionStatus::accessValues())
            ->where('is_manual', true)
            ->each(function (Subscription $old) {
                $from = $old->status;
                $old->update(['status' => SubscriptionStatus::Expired, 'ends_at' => now()]);
                $this->event($old, 'superseded', $from, SubscriptionStatus::Expired);
            });
    }

    private function redeemCoupon(Subscription $sub): void
    {
        if (! $sub->coupon_id) {
            return;
        }
        $created = CouponRedemption::query()->firstOrCreate(
            ['coupon_id' => $sub->coupon_id, 'user_id' => $sub->user_id],
            ['subscription_id' => $sub->id, 'redeemed_at' => now()],
        );
        if ($created->wasRecentlyCreated) {
            Coupon::query()->whereKey($sub->coupon_id)->increment('redeemed_count');
        }
    }

    /** @param array<string, mixed> $meta */
    private function event(Subscription $sub, string $type, ?SubscriptionStatus $from, ?SubscriptionStatus $to, ?User $actor = null, ?string $reason = null, array $meta = []): void
    {
        SubscriptionEvent::query()->create([
            'subscription_id' => $sub->id, 'actor_id' => $actor?->id, 'type' => $type,
            'from_status' => $from?->value, 'to_status' => $to?->value, 'reason' => $reason, 'meta' => $meta ?: null, 'created_at' => now(),
        ]);
    }
}
