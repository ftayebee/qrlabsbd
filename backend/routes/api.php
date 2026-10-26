<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\PublicController;
use App\Http\Controllers\Api\V1\QrCodeController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Public ---------------------------------------------------------
    Route::get('plans', [PublicController::class, 'plans']);
    Route::get('settings/public', [PublicController::class, 'settings']);
    Route::get('faqs', [PublicController::class, 'faqs']);
    Route::get('pages/{slug}', [PublicController::class, 'page'])->where('slug', '[a-z0-9-]+');
    Route::get('public/qr/{code}', [PublicController::class, 'hostedPage'])->middleware('throttle:redirects');
    Route::get('public/qr/{code}/vcard', [PublicController::class, 'vcard'])->middleware('throttle:redirects');
    Route::middleware('throttle:public-forms')->group(function () {
        Route::post('public/qr/{code}/report', [PublicController::class, 'report']);
        Route::post('contact', [PublicController::class, 'contact']);
    });
    Route::post('webhooks/stripe', [WebhookController::class, 'stripe']);

    // ---- Authentication -------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password', [AuthController::class, 'resetPassword']);
        });
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
            Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed:relative')->name('verification.verify');
        });
    });

    // ---- Customer -------------------------------------------------------
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::get('account/usage', [AccountController::class, 'usage']);
        Route::patch('account/profile', [AccountController::class, 'updateProfile']);
        Route::put('account/password', [AccountController::class, 'updatePassword']);
        Route::get('account/notifications', [AccountController::class, 'notifications']);
        Route::put('account/notifications', [AccountController::class, 'updateNotifications']);
        Route::delete('account', [AccountController::class, 'destroy']);

        Route::get('dashboard/summary', DashboardController::class);

        Route::get('qr-codes/export', [QrCodeController::class, 'export']);
        Route::post('qr-codes/bulk', [QrCodeController::class, 'bulk']);
        Route::get('qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
        Route::post('qr-codes', [QrCodeController::class, 'store'])->middleware('throttle:qr-create')->name('qr-codes.store');
        Route::get('qr-codes/{qrCode}', [QrCodeController::class, 'show'])->name('qr-codes.show');
        Route::patch('qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('qr-codes.update');
        Route::delete('qr-codes/{qrCode}', [QrCodeController::class, 'destroy'])->name('qr-codes.destroy');
        Route::post('qr-codes/{qrCode}/duplicate', [QrCodeController::class, 'duplicate'])->middleware('throttle:qr-create')->name('qr-codes.duplicate');
        Route::patch('qr-codes/{qrCode}/status', [QrCodeController::class, 'status']);
        Route::get('qr-codes/{qrCode}/history', [QrCodeController::class, 'history']);

        Route::post('files', [FileController::class, 'store'])->middleware('throttle:uploads');
        Route::delete('files/{file}', [FileController::class, 'destroy']);

        Route::apiResource('folders', FolderController::class)->except('show');
        Route::apiResource('campaigns', CampaignController::class)->except('show');

        Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
        Route::get('analytics/campaigns', [AnalyticsController::class, 'campaigns']);
        Route::get('analytics/export', [AnalyticsController::class, 'export']);
        Route::get('analytics/qr-codes/{qrCode}', [AnalyticsController::class, 'qrCode']);

        Route::prefix('billing')->group(function () {
            Route::get('subscription', [BillingController::class, 'subscription']);
            Route::post('checkout', [BillingController::class, 'checkout']);
            Route::get('checkout/{sessionId}/status', [BillingController::class, 'checkoutStatus']);
            Route::post('change-plan', [BillingController::class, 'changePlan']);
            Route::post('cancel', [BillingController::class, 'cancel']);
            Route::post('resume', [BillingController::class, 'resume']);
            Route::get('invoices', [BillingController::class, 'invoices']);
            Route::get('portal', [BillingController::class, 'portal']);
            Route::post('coupon/validate', [BillingController::class, 'validateCoupon']);
        });

        Route::get('team', [TeamController::class, 'index']);
        Route::post('team/invite', [TeamController::class, 'invite']);
        Route::post('team/accept', [TeamController::class, 'accept']);
        Route::delete('team/{member}', [TeamController::class, 'destroy']);
    });

    // ---- Admin ----------------------------------------------------------
    Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'admin', 'throttle:api'])->group(function () {
        Route::get('overview', Admin\OverviewController::class)->middleware('permission:dashboard.view');

        Route::middleware('permission:customers.view')->group(function () {
            Route::get('customers', [Admin\CustomerController::class, 'index']);
            Route::get('customers/{user}', [Admin\CustomerController::class, 'show']);
        });
        Route::middleware('permission:customers.manage')->group(function () {
            Route::patch('customers/{user}', [Admin\CustomerController::class, 'update']);
            Route::post('customers/{user}/quota-overrides', [Admin\CustomerController::class, 'storeOverride']);
            Route::delete('customers/{user}/quota-overrides/{override}', [Admin\CustomerController::class, 'destroyOverride']);
            Route::put('customers/{user}/roles', [Admin\CustomerController::class, 'syncRoles'])->middleware('permission:roles.manage');
        });

        Route::middleware('permission:subscriptions.view')->group(function () {
            Route::get('subscriptions', [Admin\SubscriptionController::class, 'index']);
            Route::get('subscriptions/{subscription}', [Admin\SubscriptionController::class, 'show']);
        });
        Route::middleware('permission:subscriptions.manage')->group(function () {
            Route::post('customers/{user}/grant-plan', [Admin\SubscriptionController::class, 'grant']);
            Route::post('subscriptions/{subscription}/cancel', [Admin\SubscriptionController::class, 'cancel']);
            Route::post('subscriptions/{subscription}/extend-trial', [Admin\SubscriptionController::class, 'extendTrial']);
            Route::post('subscriptions/{subscription}/sync', [Admin\SubscriptionController::class, 'sync']);
        });

        Route::middleware('permission:plans.manage')->group(function () {
            Route::apiResource('plans', Admin\PlanController::class);
            Route::post('plans/{plan}/duplicate', [Admin\PlanController::class, 'duplicate']);
        });

        Route::apiResource('coupons', Admin\CouponController::class)->except('show')->middleware('permission:coupons.manage');

        Route::middleware('permission:payments.view')->group(function () {
            Route::get('payments', [Admin\PaymentController::class, 'index']);
            Route::get('invoices', [Admin\PaymentController::class, 'invoices']);
            Route::get('webhook-events', [Admin\PaymentController::class, 'webhookEvents']);
        });
        Route::middleware('permission:payments.manage')->group(function () {
            Route::post('payments/{payment}/refund', [Admin\PaymentController::class, 'refund']);
            Route::post('webhook-events/{event}/retry', [Admin\PaymentController::class, 'retryWebhook']);
        });

        Route::middleware('permission:moderation.manage')->group(function () {
            Route::get('qr-codes', [Admin\ModerationController::class, 'qrCodes']);
            Route::patch('qr-codes/{qrCode}/moderation', [Admin\ModerationController::class, 'moderate']);
            Route::get('abuse-reports', [Admin\ModerationController::class, 'reports']);
            Route::patch('abuse-reports/{report}', [Admin\ModerationController::class, 'resolve']);
            Route::get('storage', [Admin\ModerationController::class, 'storage']);
        });

        Route::middleware('permission:content.manage')->group(function () {
            Route::get('faqs', [Admin\ContentController::class, 'faqs']);
            Route::post('faqs', [Admin\ContentController::class, 'storeFaq']);
            Route::put('faqs/{faq}', [Admin\ContentController::class, 'updateFaq']);
            Route::delete('faqs/{faq}', [Admin\ContentController::class, 'destroyFaq']);
            Route::get('content-pages', [Admin\ContentController::class, 'pages']);
            Route::post('content-pages', [Admin\ContentController::class, 'storePage']);
            Route::put('content-pages/{page}', [Admin\ContentController::class, 'updatePage']);
            Route::delete('content-pages/{page}', [Admin\ContentController::class, 'destroyPage']);
            Route::get('email-templates', [Admin\ContentController::class, 'templates']);
            Route::put('email-templates/{template}', [Admin\ContentController::class, 'updateTemplate']);
            Route::get('contact-messages', [Admin\ContentController::class, 'messages']);
            Route::patch('contact-messages/{message}', [Admin\ContentController::class, 'updateMessage']);
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [Admin\SettingsController::class, 'index']);
            Route::put('settings', [Admin\SettingsController::class, 'update']);
        });

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [Admin\RoleController::class, 'index']);
            Route::post('roles', [Admin\RoleController::class, 'store']);
            Route::put('roles/{role}', [Admin\RoleController::class, 'update']);
            Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy']);
        });

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->middleware('permission:audit.view');
    });
});
