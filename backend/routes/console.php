<?php

use App\Enums\SubscriptionStatus;
use App\Models\QrScan;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('qrio:prune-scans', function () {
    $cutoff = now()->subDays((int) config('qrio.analytics.raw_retention_days'))->startOfDay();
    $deleted = 0;
    // Chunked deletes keep locks short on large tables. Aggregates are untouched.
    do {
        $batch = QrScan::query()->where('scanned_at', '<', $cutoff)->limit(5000)->delete();
        $deleted += $batch;
    } while ($batch > 0);
    $this->info("Pruned {$deleted} raw scan events older than {$cutoff->toDateString()}.");
})->purpose('Delete raw scan events past the retention window');

Artisan::command('qrio:expire-subscriptions', function (SubscriptionService $subscriptions) {
    $expired = $subscriptions->expireManual();
    // Checkouts abandoned for more than a day will never complete.
    $abandoned = Subscription::query()->where('status', SubscriptionStatus::Incomplete)->whereNull('provider_subscription_id')
        ->where('created_at', '<', now()->subDay())->update(['status' => SubscriptionStatus::Expired]);
    $this->info("Expired {$expired} manual subscriptions and {$abandoned} abandoned checkouts.");
})->purpose('End manual subscriptions past their end date');

Schedule::command('qrio:prune-scans')->dailyAt('03:15')->withoutOverlapping()->onOneServer();
Schedule::command('qrio:expire-subscriptions')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
// Shared hosting has no long-running worker: the per-minute schedule:run cron drains
// the queue (emails, scan recording, webhooks) and exits before the next run starts.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5);
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
