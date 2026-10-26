<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function index(Request $request): JsonResponse
    {
        $campaigns = $request->user()->campaigns()
            ->withCount('qrCodes')
            ->withSum('qrCodes as total_scans', 'total_scans')
            ->latest()->get();

        return response()->json(['data' => $campaigns->map(fn (Campaign $c) => $this->present($c))]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->entitlements->for($request->user())->allows('campaigns')) {
            throw new PlanLimitException('campaigns', 'Campaigns are available on paid plans.');
        }
        $campaign = $request->user()->campaigns()->create($this->validated($request));

        return response()->json(['data' => $this->present($campaign)], 201);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);
        $campaign->update($this->validated($request));

        return response()->json(['data' => $this->present($campaign)]);
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted. Its QR codes were kept.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Campaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'color' => $c->color,
            'startsOn' => $c->starts_on?->toDateString(),
            'endsOn' => $c->ends_on?->toDateString(),
            'qrCount' => (int) ($c->qr_codes_count ?? 0),
            'totalScans' => (int) ($c->total_scans ?? 0),
            'createdAt' => $c->created_at?->toIso8601String(),
        ];
    }
}
