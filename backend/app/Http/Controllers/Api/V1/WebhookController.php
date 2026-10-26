<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\Billing\Gateway\InvalidWebhookSignature;
use App\Services\Billing\Gateway\PaymentGateway;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function stripe(Request $request, PaymentGateway $gateway): JsonResponse
    {
        try {
            $payload = $gateway->parseWebhook($request);
        } catch (InvalidWebhookSignature) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Idempotency: (provider, event_id) is unique. A replayed event is acknowledged without reprocessing.
        try {
            $event = WebhookEvent::query()->create([
                'provider' => $gateway->name(),
                'event_id' => $payload->id,
                'type' => $payload->type,
                'payload' => $payload->raw,
            ]);
        } catch (UniqueConstraintViolationException) {
            $event = WebhookEvent::query()->where(['provider' => $gateway->name(), 'event_id' => $payload->id])->firstOrFail();
            if ($event->status !== 'failed') {
                return response()->json(['received' => true, 'duplicate' => true]);
            }
        }

        ProcessWebhookEvent::dispatch($event->id);

        return response()->json(['received' => true]);
    }
}
