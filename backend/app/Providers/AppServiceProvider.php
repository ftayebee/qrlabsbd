<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Folder;
use App\Models\QrCode;
use App\Models\QrFile;
use App\Policies\CampaignPolicy;
use App\Policies\FolderPolicy;
use App\Policies\QrCodePolicy;
use App\Policies\QrFilePolicy;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\Gateway\StripeGateway;
use App\Services\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->scoped(EntitlementService::class);
        // Add further gateways here, selected by BILLING_GATEWAY.
        $this->app->bind(PaymentGateway::class, StripeGateway::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(10)->letters()->numbers());

        Gate::policy(QrCode::class, QrCodePolicy::class);
        Gate::policy(QrFile::class, QrFilePolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);

        $this->rateLimits();
    }

    private function rateLimits(): void
    {
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => [
            Limit::perMinute(10)->by('auth-ip:'.$r->ip()),
            Limit::perMinute(5)->by('auth-email:'.strtolower((string) $r->input('email'))),
        ]);
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('qr-create', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        // Generous per-IP cap: venues share one public IP, but floods are cut off.
        RateLimiter::for('redirects', fn (Request $r) => Limit::perMinute(300)->by('scan:'.$r->ip()));
        RateLimiter::for('public-forms', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
    }
}
