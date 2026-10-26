<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::query()->with('plans:id,name')
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', '%'.strtoupper((string) $s).'%')->orWhere('name', 'like', "%{$s}%")))
            ->latest()->paginate(25);

        return response()->json([
            'data' => $coupons->getCollection()->map(fn (Coupon $c) => $this->present($c)),
            'meta' => ['current_page' => $coupons->currentPage(), 'last_page' => $coupons->lastPage(), 'per_page' => $coupons->perPage(), 'total' => $coupons->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $coupon = Coupon::query()->create(collect($data)->except('plan_ids')->all() + ['code' => strtoupper($data['code'])]);
        $coupon->plans()->sync($data['plan_ids'] ?? []);
        $this->audit->log($request->user(), 'coupon.created', $coupon, null, ['code' => $coupon->code]);

        return response()->json(['data' => $this->present($coupon->load('plans:id,name'))], 201);
    }

    /**
     * Discount terms are immutable once a coupon exists at the provider;
     * only availability (dates, limits, active flag, plans) may change.
     */
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'plan_ids' => ['array'],
            'plan_ids.*' => ['integer', 'exists:plans,id'],
        ]);
        $coupon->update(collect($data)->except('plan_ids')->all());
        if (array_key_exists('plan_ids', $data)) {
            $coupon->plans()->sync($data['plan_ids']);
        }
        $this->audit->log($request->user(), 'coupon.updated', $coupon, null, $data);

        return response()->json(['data' => $this->present($coupon->load('plans:id,name'))]);
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        if ($coupon->redeemed_count > 0) {
            $coupon->update(['is_active' => false]);
            $message = 'Coupon has redemptions, so it was deactivated instead of deleted.';
        } else {
            $coupon->delete();
            $message = 'Coupon deleted.';
        }
        $this->audit->log($request->user(), 'coupon.removed', $coupon);

        return response()->json(['message' => $message]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1', Rule::when($request->input('type') === 'percent', ['max:100'])],
            'currency' => ['nullable', 'required_if:type,fixed', 'string', 'size:3', 'alpha'],
            'duration' => ['required', Rule::in(['once', 'repeating', 'forever'])],
            'duration_months' => ['nullable', 'required_if:duration,repeating', 'integer', 'between:1,36'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'plan_ids' => ['array'],
            'plan_ids.*' => ['integer', 'exists:plans,id'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Coupon $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'type' => $c->type, 'value' => $c->value,
            'currency' => $c->currency, 'duration' => $c->duration, 'durationMonths' => $c->duration_months,
            'maxRedemptions' => $c->max_redemptions, 'redeemedCount' => $c->redeemed_count,
            'startsAt' => $c->starts_at?->toIso8601String(), 'expiresAt' => $c->expires_at?->toIso8601String(),
            'isActive' => $c->is_active, 'plans' => $c->relationLoaded('plans') ? $c->plans->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]) : [],
            'createdAt' => $c->created_at?->toIso8601String(),
        ];
    }
}
