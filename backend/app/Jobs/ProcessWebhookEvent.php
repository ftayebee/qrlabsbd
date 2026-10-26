<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Billing\Webhooks\StripeWebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a stored webhook exactly once. The row lock plus the processed
 * status check make concurrent or repeated deliveries no-ops, and all billing
 * changes for one event commit or roll back together.
 */
class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue('billing');
    }

    public function handle(StripeWebhookProcessor $stripe): void
    {
        try {
            DB::transaction(function () use ($stripe) {
                $event = WebhookEvent::query()->whereKey($this->webhookEventId)->lockForUpdate()->first();
                if (! $event || in_array($event->status, ['processed', 'ignored'], true)) {
                    return;
                }

                $payload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
                $handled = match ($event->provider) {
                    'stripe' => $stripe->handle($event->type, $payload['data']['object'] ?? []),
                    default => false,
                };

                $event->update([
                    'status' => $handled ? 'processed' : 'ignored',
                    'attempts' => $event->attempts + 1,
                    'processed_at' => now(),
                    'error' => null,
                ]);
            });
        } catch (Throwable $e) {
            // Recorded outside the rolled-back transaction so failures stay visible to admins.
            WebhookEvent::query()->whereKey($this->webhookEventId)->update([
                'status' => 'failed',
                'attempts' => DB::raw('attempts + 1'),
                'error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
            Log::error('Webhook processing failed', ['webhook_event_id' => $this->webhookEventId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
