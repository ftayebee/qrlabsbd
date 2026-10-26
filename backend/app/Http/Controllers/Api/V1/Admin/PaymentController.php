<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\AuditLogger;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return PaymentResource::collection(Payment::query()->with(['user:id,name,email', 'invoice:id,number'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('provider_payment_id', $s)->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$s}%"))))
            ->latest()->paginate(25)->withQueryString());
    }

    public function invoices(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection(Invoice::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('issued_at')->paginate(25)->withQueryString());
    }

    /** Requests a refund at the provider. The local record is updated when the provider confirms it. */
    public function refund(Request $request, Payment $payment, PaymentGateway $gateway, SubscriptionService $subscriptions): JsonResponse
    {
        $remaining = $payment->amount - $payment->refunded_amount;
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.max(1, $remaining)],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        abort_unless(in_array($payment->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true) && $remaining > 0, 422, 'This payment cannot be refunded.');

        $refund = $gateway->refund($payment, $data['amount'], $data['reason']);
        $subscriptions->recordRefund($refund);
        $this->audit->log($request->user(), 'payment.refunded', $payment, $data['reason'], ['amount' => $data['amount'], 'currency' => $payment->currency]);

        return response()->json(['message' => 'Refund requested.', 'data' => new PaymentResource($payment->fresh())]);
    }

    public function webhookEvents(Request $request): JsonResponse
    {
        $events = WebhookEvent::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(50, ['id', 'provider', 'event_id', 'type', 'status', 'attempts', 'error', 'processed_at', 'created_at']);

        return response()->json([
            'data' => $events->items(),
            'meta' => ['current_page' => $events->currentPage(), 'last_page' => $events->lastPage(), 'per_page' => $events->perPage(), 'total' => $events->total()],
        ]);
    }

    public function retryWebhook(Request $request, WebhookEvent $event): JsonResponse
    {
        abort_unless($event->status === 'failed', 422, 'Only failed events can be retried.');
        ProcessWebhookEvent::dispatch($event->id);
        $this->audit->log($request->user(), 'webhook.retried', $event);

        return response()->json(['message' => 'Retry queued.']);
    }
}
