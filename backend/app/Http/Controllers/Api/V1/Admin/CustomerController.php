<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\QuotaOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\Entitlements;
use App\Services\Qr\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly EntitlementService $entitlements) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'plan' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        $users = User::query()
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")->orWhere('id', (int) $s)))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['plan'] ?? null, fn ($q, $p) => $q->whereHas('activeSubscription.plan', fn ($w) => $w->where('slug', $p)))
            ->with(['activeSubscription.plan:id,name,slug', 'roles:id,slug'])
            ->withCount('qrCodes')
            ->latest()->paginate($f['per_page'] ?? 25)->withQueryString();

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status,
                'emailVerified' => $u->email_verified_at !== null,
                'plan' => $u->activeSubscription?->plan?->name ?? 'Free',
                'subscriptionStatus' => $u->activeSubscription?->status->value,
                'qrCount' => $u->qr_codes_count,
                'roles' => $u->roles->pluck('slug'),
                'lastLoginAt' => $u->last_login_at?->toIso8601String(),
                'createdAt' => $u->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total()],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,slug,name', 'subscriptions' => fn ($q) => $q->with(['plan', 'price', 'events.actor:id,name'])->latest('id')->limit(20)]);
        $e = $this->entitlements->for($user);

        return response()->json(['data' => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status,
            'emailVerified' => $user->email_verified_at !== null, 'timezone' => $user->timezone,
            'providerCustomerId' => $user->provider_customer_id,
            'roles' => $user->roles->map(fn (Role $r) => ['id' => $r->id, 'slug' => $r->slug, 'name' => $r->name]),
            'createdAt' => $user->created_at?->toIso8601String(), 'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            'plan' => $e->plan->name,
            'entitlements' => $e->toArray(),
            'usage' => $this->entitlements->usage($user),
            'overrides' => QuotaOverride::query()->where('user_id', $user->id)->get()->map(fn (QuotaOverride $o) => [
                'id' => $o->id, 'key' => $o->key, 'value' => $o->value, 'reason' => $o->reason, 'expiresAt' => $o->expires_at?->toIso8601String(),
            ]),
            'subscriptions' => SubscriptionResource::collection($user->subscriptions),
            'invoices' => InvoiceResource::collection($user->invoices()->latest('issued_at')->limit(20)->get()),
            'payments' => PaymentResource::collection($user->payments()->latest()->limit(20)->get()),
        ]]);
    }

    public function update(Request $request, User $user, QrCodeService $qr): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        abort_if($user->is($request->user()), 422, 'You cannot change your own account status.');

        $old = $user->status;
        $user->update(['status' => $data['status']]);
        // Redirect caches carry the owner's status, so clear them for immediate effect.
        $user->qrCodes()->whereNotNull('short_code')->get()->each(fn ($code) => $qr->forgetCache($code));
        $this->audit->log($request->user(), 'customer.status_changed', $user, $data['reason'], ['from' => $old, 'to' => $data['status']]);

        return response()->json(['message' => 'Customer updated.']);
    }

    public function storeOverride(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(Entitlements::DEFAULTS))],
            'value' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $override = QuotaOverride::query()->updateOrCreate(
            ['user_id' => $user->id, 'key' => $data['key']],
            ['value' => $data['value'], 'reason' => $data['reason'], 'expires_at' => $data['expires_at'] ?? null, 'created_by' => $request->user()->id],
        );
        $this->entitlements->forget($user);
        $this->audit->log($request->user(), 'customer.quota_override', $user, $data['reason'], ['key' => $data['key'], 'value' => $data['value']]);

        return response()->json(['data' => ['id' => $override->id]], 201);
    }

    public function destroyOverride(Request $request, User $user, QuotaOverride $override): JsonResponse
    {
        abort_unless($override->user_id === $user->id, 404);
        $override->delete();
        $this->audit->log($request->user(), 'customer.quota_override_removed', $user, null, ['key' => $override->key]);

        return response()->json(['message' => 'Override removed.']);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['roles' => ['present', 'array'], 'roles.*' => ['integer', 'exists:roles,id'], 'reason' => ['required', 'string', 'max:255']]);
        $superAdmin = Role::query()->where('slug', 'super-admin')->value('id');
        if ($user->is($request->user()) && $superAdmin && ! in_array($superAdmin, $data['roles'], true) && $user->roles()->whereKey($superAdmin)->exists()) {
            abort(422, 'You cannot remove your own super-admin role.');
        }
        $before = $user->roles()->pluck('slug')->all();
        $user->roles()->sync($data['roles']);
        $user->flushAccessCache();
        $this->audit->log($request->user(), 'customer.roles_changed', $user, $data['reason'], ['from' => $before, 'to' => $user->roles()->pluck('slug')->all()]);

        return response()->json(['message' => 'Roles updated.']);
    }
}
