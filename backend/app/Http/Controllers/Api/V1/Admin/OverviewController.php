<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AbuseReport;
use App\Models\Payment;
use App\Models\QrCode;
use App\Models\Refund;
use App\Models\ScanAggregate;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin KPIs. Revenue comes only from reconciled payments (status set by
 * provider webhooks), grouped by currency, never from plan list prices.
 */
class OverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
        $since = CarbonImmutable::now()->subDays($days)->startOfDay();

        $revenue = Payment::query()
            ->whereIn('status', [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded])
            ->where('paid_at', '>=', $since)
            ->groupBy('currency')
            ->get(['currency', DB::raw('SUM(amount) as gross'), DB::raw('SUM(refunded_amount) as refunded'), DB::raw('COUNT(*) as count')])
            ->map(fn ($r) => ['currency' => $r->currency, 'gross' => (int) $r->gross, 'refunded' => (int) $r->refunded, 'net' => (int) $r->gross - (int) $r->refunded, 'payments' => (int) $r->count]);

        $revenueSeries = Payment::query()
            ->where('status', '!=', PaymentStatus::Failed)->where('paid_at', '>=', $since)
            ->groupBy(DB::raw('DATE(paid_at)'), 'currency')
            ->orderBy(DB::raw('DATE(paid_at)'))
            ->get([DB::raw('DATE(paid_at) as date'), 'currency', DB::raw('SUM(amount - refunded_amount) as net')])
            ->map(fn ($r) => ['date' => (string) $r->date, 'currency' => $r->currency, 'net' => (int) $r->net]);

        $scanSeries = ScanAggregate::query()->where('dimension', 'total')->where('date', '>=', $since->toDateString())
            ->groupBy('date')->orderBy('date')
            ->get(['date', DB::raw('SUM(scans) as scans')])
            ->map(fn ($r) => ['date' => substr((string) $r->getRawOriginal('date'), 0, 10), 'scans' => (int) $r->scans]);

        $byPlan = Subscription::query()->whereIn('subscriptions.status', SubscriptionStatus::accessValues())
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('plans.name')->get(['plans.name', DB::raw('COUNT(*) as c')])
            ->map(fn ($r) => ['plan' => $r->name, 'count' => (int) $r->c]);

        return response()->json(['data' => [
            'range' => ['days' => $days, 'since' => $since->toDateString()],
            'customers' => [
                'total' => User::query()->count(),
                'new' => User::query()->where('created_at', '>=', $since)->count(),
                'suspended' => User::query()->where('status', 'suspended')->count(),
            ],
            'subscriptions' => [
                'active' => Subscription::query()->whereIn('status', SubscriptionStatus::accessValues())->count(),
                'trialing' => Subscription::query()->where('status', SubscriptionStatus::Trialing)->count(),
                'pastDue' => Subscription::query()->where('status', SubscriptionStatus::PastDue)->count(),
                'new' => SubscriptionEvent::query()->where('type', 'status_changed')->whereIn('to_status', ['active', 'trialing'])->whereIn('from_status', ['incomplete'])->where('created_at', '>=', $since)->count(),
                'cancellations' => SubscriptionEvent::query()->whereIn('type', ['canceled', 'cancel_scheduled'])->where('created_at', '>=', $since)->count(),
                'byPlan' => $byPlan,
            ],
            'revenue' => $revenue,
            'revenueSeries' => $revenueSeries,
            'refunds' => (int) Refund::query()->where('created_at', '>=', $since)->count(),
            'failedPayments' => Payment::query()->where('status', PaymentStatus::Failed)->where('created_at', '>=', $since)->count(),
            'qr' => [
                'total' => QrCode::query()->count(),
                'created' => QrCode::query()->where('created_at', '>=', $since)->count(),
                'dynamic' => QrCode::query()->where('mode', 'dynamic')->count(),
                'scans' => (int) $scanSeries->sum('scans'),
                'scanSeries' => $scanSeries,
            ],
            'attention' => [
                'openReports' => AbuseReport::query()->where('status', 'open')->count(),
                'failedWebhooks' => WebhookEvent::query()->where('status', 'failed')->count(),
            ],
        ]]);
    }
}
