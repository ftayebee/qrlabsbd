<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\QrCode;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\SubscriptionService;
use Tests\Fakes\FakeStripeGateway;
use Tests\TestCase;

class BillingTest extends TestCase
{
    private FakeStripeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeStripeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function proMonthly(): PlanPrice
    {
        return Plan::query()->where('slug', 'pro')->firstOrFail()->prices()->where('interval', 'month')->firstOrFail();
    }

    /** Signs a payload exactly as Stripe does (v1 scheme). */
    private function postWebhook(array $event, ?string $secret = 'whsec_test_secret')
    {
        $payload = json_encode($event);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", (string) $secret);

        return $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    private function checkoutAndComplete(User $user): Subscription
    {
        $price = $this->proMonthly();
        $this->actingAs($user)->postJson('/api/v1/billing/checkout', ['price_id' => $price->id])->assertOk()->assertJsonPath('data.url', fn ($u) => str_starts_with($u, 'https://checkout.stripe.test/'));
        $pending = Subscription::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->gateway->completeCheckout($pending->provider_checkout_id, $pending, $price);

        return $pending;
    }

    public function test_success_redirect_alone_never_grants_a_plan(): void
    {
        $user = $this->customer();
        $price = $this->proMonthly();
        $this->actingAs($user)->postJson('/api/v1/billing/checkout', ['price_id' => $price->id])->assertOk();
        $pending = Subscription::query()->where('user_id', $user->id)->firstOrFail();

        // Provider has not confirmed: status stays incomplete and entitlements stay free.
        $this->getJson("/api/v1/billing/checkout/{$pending->provider_checkout_id}/status")->assertOk()->assertJsonPath('data.active', false);
        $this->getJson('/api/v1/account/usage')->assertJsonPath('data.plan.slug', 'free');
    }

    public function test_checkout_activates_after_server_side_verification(): void
    {
        $user = $this->customer();
        $pending = $this->checkoutAndComplete($user);

        $this->getJson("/api/v1/billing/checkout/{$pending->provider_checkout_id}/status")->assertOk()->assertJsonPath('data.active', true);
        $this->getJson('/api/v1/account/usage')->assertJsonPath('data.plan.slug', 'pro')->assertJsonPath('data.entitlements.dynamic_codes', 50);
        $this->assertDatabaseHas('subscription_events', ['subscription_id' => $pending->id, 'type' => 'status_changed', 'to_status' => 'active']);
    }

    public function test_unverified_customers_cannot_check_out(): void
    {
        $this->actingAs($this->customer(['email_verified_at' => null]))->postJson('/api/v1/billing/checkout', ['price_id' => $this->proMonthly()->id])->assertForbidden();
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $this->postWebhook(['id' => 'evt_1', 'type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_x']]], 'whsec_wrong')->assertStatus(400);
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_webhooks_are_idempotent(): void
    {
        $user = $this->customer();
        $pending = $this->checkoutAndComplete($user);
        $event = ['id' => 'evt_checkout_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $pending->provider_checkout_id, 'subscription' => 'sub_'.$pending->id]]];

        $this->postWebhook($event)->assertOk()->assertJsonPath('received', true);
        $this->postWebhook($event)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame('processed', WebhookEvent::query()->value('status'));
        $this->assertSame(1, $this->gateway->fetches);
        $this->assertSame(SubscriptionStatus::Active, $pending->fresh()->status);
    }

    public function test_invoice_webhooks_record_payments_once_and_failed_payment_moves_to_past_due(): void
    {
        $user = $this->customer();
        $pending = $this->checkoutAndComplete($user);
        app(SubscriptionService::class)->reconcileCheckout($pending);
        $subId = 'sub_'.$pending->id;
        $invoice = fn (string $status, int $attempt) => ['id' => 'in_1', 'customer' => 'cus_test', 'number' => 'INV-1', 'status' => $status, 'currency' => 'usd',
            'subtotal' => 1200, 'total' => 1200, 'created' => time(), 'attempt_count' => $attempt, 'payment_intent' => 'pi_1',
            'parent' => ['subscription_details' => ['subscription' => $subId]], 'status_transitions' => ['paid_at' => $status === 'paid' ? time() : null]];

        $this->gateway->subscriptions[$subId] = $this->gateway->state($subId, SubscriptionStatus::PastDue, $this->gateway->subscriptions[$subId]->metadata);
        $this->postWebhook(['id' => 'evt_fail', 'type' => 'invoice.payment_failed', 'data' => ['object' => $invoice('open', 1)]])->assertOk();
        $this->assertSame('failed', Payment::query()->value('status')->value);
        $this->assertSame(SubscriptionStatus::PastDue, $pending->fresh()->status);
        // Past-due keeps access while the provider retries.
        $this->getJson('/api/v1/account/usage')->assertJsonPath('data.plan.slug', 'pro');

        $this->gateway->subscriptions[$subId] = $this->gateway->state($subId, SubscriptionStatus::Active, $this->gateway->subscriptions[$subId]->metadata);
        $this->postWebhook(['id' => 'evt_paid', 'type' => 'invoice.paid', 'data' => ['object' => $invoice('paid', 2)]])->assertOk();
        $this->postWebhook(['id' => 'evt_paid', 'type' => 'invoice.paid', 'data' => ['object' => $invoice('paid', 2)]])->assertOk();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('succeeded', Payment::query()->value('status')->value);
        $this->assertDatabaseHas('invoices', ['provider_invoice_id' => 'in_1', 'status' => 'paid', 'total' => 1200, 'currency' => 'USD']);
        $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonPath('data.0.number', 'INV-1');
    }

    public function test_cancellation_grace_period_then_free_limits_without_breaking_redirects(): void
    {
        $user = $this->customer();
        $pending = $this->checkoutAndComplete($user);
        app(SubscriptionService::class)->reconcileCheckout($pending);

        // On Pro, create more dynamic codes than Free allows.
        $codes = collect(range(1, 3))->map(fn ($i) => $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => "https://example.com/{$i}"]))->assertCreated()->json('data.id'));

        $this->postJson('/api/v1/billing/cancel', ['immediately' => true, 'reason' => 'Testing'])->assertOk()->assertJsonPath('data.status', 'canceled');
        $sub = $pending->fresh();
        $this->assertNotNull($sub->grace_ends_at);

        // During grace: still Pro.
        $this->getJson('/api/v1/account/usage')->assertJsonPath('data.plan.slug', 'pro')->assertJsonPath('data.inGrace', true);

        // After grace: free limits, codes read-only, but still redirecting.
        $this->travel(15)->days();
        app(\App\Services\Billing\EntitlementService::class)->forget($user);
        $this->getJson('/api/v1/account/usage')->assertJsonPath('data.plan.slug', 'free')->assertJsonPath('data.readOnly', true);
        $this->patchJson("/api/v1/qr-codes/{$codes[0]}", ['content' => ['url' => 'https://example.com/changed']])->assertStatus(402);
        $this->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'https://example.com']))->assertStatus(402);
        $this->get('/r/'.QrCode::query()->find($codes[0])->short_code)->assertRedirect('https://example.com/1');
    }

    public function test_plan_change_and_resume(): void
    {
        $user = $this->customer();
        $pending = $this->checkoutAndComplete($user);
        app(SubscriptionService::class)->reconcileCheckout($pending);
        $business = Plan::query()->where('slug', 'business')->firstOrFail()->prices()->where('interval', 'month')->firstOrFail();

        $this->actingAs($user)->postJson('/api/v1/billing/change-plan', ['price_id' => $business->id])->assertOk()->assertJsonPath('data.plan.slug', 'business');
        $this->postJson('/api/v1/billing/cancel', [])->assertOk()->assertJsonPath('data.cancelAtPeriodEnd', true);
        $this->postJson('/api/v1/billing/resume')->assertOk()->assertJsonPath('data.cancelAtPeriodEnd', false);
    }

    public function test_coupon_validation_and_admin_refund(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/v1/admin/coupons', ['code' => 'launch20', 'name' => 'Launch', 'type' => 'percent', 'value' => 20, 'duration' => 'once'])->assertCreated();

        $user = $this->customer();
        $this->actingAs($user)->postJson('/api/v1/billing/coupon/validate', ['code' => 'LAUNCH20', 'price_id' => $this->proMonthly()->id])
            ->assertOk()->assertJsonPath('data.discount', 240)->assertJsonPath('data.total', 960);
        $this->postJson('/api/v1/billing/coupon/validate', ['code' => 'NOPE', 'price_id' => $this->proMonthly()->id])->assertUnprocessable();

        $payment = Payment::query()->create(['user_id' => $user->id, 'provider' => 'stripe', 'provider_payment_id' => 'pi_r', 'status' => 'succeeded', 'currency' => 'USD', 'amount' => 1200]);
        $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 500, 'reason' => 'Goodwill'])->assertOk();
        $this->assertSame('partially_refunded', $payment->fresh()->status->value);
        $this->actingAs($admin)->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 800, 'reason' => 'Too much'])->assertUnprocessable();
    }
}
