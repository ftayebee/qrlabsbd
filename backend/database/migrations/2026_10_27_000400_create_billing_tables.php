<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * All monetary amounts are stored as integers in the currency's minor unit
 * (cents, pence…) alongside an explicit ISO 4217 currency code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            // draft | published | archived
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->boolean('highlight')->default(false);
            $table->string('cta_label', 40)->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 8);
            $table->char('currency', 3);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('promo_amount')->nullable();
            $table->timestamp('promo_ends_at')->nullable();
            $table->string('provider_price_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['plan_id', 'interval', 'currency']);
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            // boolean | limit | text. Limits use -1 for unlimited.
            $table->string('type', 10);
            $table->string('value', 120);
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['plan_id', 'key']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            // percent | fixed
            $table->string('type', 10);
            // Percent: whole percentage. Fixed: minor units in `currency`.
            $table->unsignedInteger('value');
            $table->char('currency', 3)->nullable();
            // once | repeating | forever
            $table->string('duration', 10)->default('once');
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('provider_coupon_id')->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_plan', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'plan_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_price_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            // incomplete | trialing | active | past_due | canceled | expired
            $table->string('status', 16)->index();
            $table->string('provider', 20);
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('provider_checkout_id')->nullable()->unique();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
            $table->index(['subscription_id', 'created_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->unique();
            $table->string('provider', 20);
            $table->string('provider_invoice_id')->nullable()->unique();
            // draft | open | paid | void | uncollectible
            $table->string('status', 16)->index();
            $table->char('currency', 3);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('hosted_url', 1024)->nullable();
            $table->string('pdf_url', 1024)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'issued_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20);
            $table->string('provider_payment_id')->unique();
            // pending | succeeded | failed | refunded | partially_refunded
            $table->string('status', 20)->index();
            $table->char('currency', 3);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider_refund_id')->unique();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('redeemed_at');
            $table->unique(['coupon_id', 'user_id']);
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 40);
            // YYYY-MM for monthly metrics; "lifetime" for cumulative ones.
            $table->string('period', 10);
            $table->unsignedBigInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'metric', 'period']);
        });

        Schema::create('quota_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('value', 120);
            $table->string('reason', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id');
            $table->string('type', 100);
            $table->longText('payload');
            // received | processing | processed | failed | ignored
            $table->string('status', 16)->default('received')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_events', 'quota_overrides', 'usage_records', 'coupon_redemptions', 'refunds', 'payments', 'invoices', 'subscription_events', 'subscriptions', 'coupon_plan', 'coupons', 'plan_features', 'plan_prices', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
