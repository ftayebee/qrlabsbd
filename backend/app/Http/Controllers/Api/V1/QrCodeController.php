<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Qr\StoreQrCodeRequest;
use App\Http\Requests\Qr\UpdateQrCodeRequest;
use App\Http\Resources\QrCodeResource;
use App\Models\QrCode;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Billing\EntitlementService;
use App\Services\Qr\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrCodeController extends Controller
{
    public function __construct(
        private readonly QrCodeService $service,
        private readonly EntitlementService $entitlements,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:20'],
            'mode' => ['nullable', Rule::in(['static', 'dynamic'])],
            'status' => ['nullable', Rule::in(['active', 'paused', 'archived', 'all'])],
            'folder_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'name', '-name', 'total_scans', '-total_scans', 'last_scanned_at', '-last_scanned_at'])],
            'per_page' => ['nullable', 'integer', 'between:6,100'],
            'workspace' => ['nullable', 'integer'],
        ]);

        $sort = $filters['sort'] ?? '-created_at';
        $codes = $this->scoped($request, $filters)
            ->with(['campaign:id,name,color', 'folder:id,name', 'design'])
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 24)
            ->withQueryString();

        return QrCodeResource::collection($codes);
    }

    public function store(StoreQrCodeRequest $request): JsonResponse
    {
        $qr = $this->service->create($request->user(), $request->qrData());

        return (new QrCodeResource($qr))->response()->setStatusCode(201);
    }

    public function show(Request $request, QrCode $qrCode): QrCodeResource
    {
        $this->authorize('view', $qrCode);
        $qrCode->load(['design', 'campaign:id,name,color', 'folder:id,name', 'destinations']);

        return (new QrCodeResource($qrCode))->readOnly($this->readOnlyReason($qrCode));
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): QrCodeResource
    {
        $qr = $this->service->update($qrCode, $request->user(), $request->qrData());

        return (new QrCodeResource($qr))->readOnly($this->readOnlyReason($qr));
    }

    public function destroy(Request $request, QrCode $qrCode): JsonResponse
    {
        $this->authorize('delete', $qrCode);
        $this->service->delete($qrCode);

        return response()->json(['message' => 'QR code deleted.']);
    }

    public function duplicate(Request $request, QrCode $qrCode): JsonResponse
    {
        // Owner-only: the copy is created in, and counts against, the owner's account.
        $this->authorize('delete', $qrCode);
        $qrCode->load('design');

        return (new QrCodeResource($this->service->duplicate($qrCode)))->response()->setStatusCode(201);
    }

    public function status(Request $request, QrCode $qrCode): QrCodeResource
    {
        $this->authorize('update', $qrCode);
        $data = $request->validate(['status' => ['required', Rule::enum(QrStatus::class)]]);
        if (! $qrCode->isDynamic() && $data['status'] === 'paused') {
            abort(422, 'Static codes cannot be paused because their content is printed directly in the code.');
        }

        return new QrCodeResource($this->service->setStatus($qrCode, $request->user(), QrStatus::from($data['status']))->load('design'));
    }

    public function history(Request $request, QrCode $qrCode): JsonResponse
    {
        $this->authorize('view', $qrCode);
        $changes = $qrCode->changes()->with('user:id,name')->limit(50)->get();

        return response()->json(['data' => $changes->map(fn ($c) => [
            'id' => $c->id, 'summary' => $c->summary, 'user' => $c->user?->name, 'createdAt' => $c->created_at?->toIso8601String(),
        ])]);
    }

    /** Archive, restore, delete or move several of the user's own codes at once. */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['archive', 'restore', 'delete', 'move'])],
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('user_id', $request->user()->id)],
        ]);

        $codes = QrCode::query()->where('user_id', $request->user()->id)->whereIn('id', $data['ids'])->get();
        foreach ($codes as $qr) {
            match ($data['action']) {
                'archive' => $this->service->setStatus($qr, $request->user(), QrStatus::Archived),
                'restore' => $this->service->setStatus($qr, $request->user(), QrStatus::Active),
                'delete' => $this->service->delete($qr),
                'move' => $qr->update(['folder_id' => $data['folder_id'] ?? null]),
            };
        }

        return response()->json(['message' => 'Updated '.$codes->count().' QR codes.', 'count' => $codes->count()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->entitlements->for($user)->allows('csv_export'), 402, 'CSV export is available on paid plans.');

        return response()->streamDownload(function () use ($user) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'type', 'mode', 'status', 'short_url', 'campaign', 'total_scans', 'unique_scans_estimate', 'last_scanned_at', 'created_at']);
            QrCode::query()->where('user_id', $user->id)->with('campaign:id,name')->lazyById(500)->each(function (QrCode $qr) use ($out) {
                fputcsv($out, [
                    $qr->id, AnalyticsService::csvSafe($qr->name), $qr->type->value, $qr->mode->value, $qr->status->value, $qr->shortUrl(),
                    AnalyticsService::csvSafe((string) $qr->campaign?->name), $qr->total_scans, $qr->unique_scans,
                    $qr->last_scanned_at?->toIso8601String(), $qr->created_at?->toIso8601String(),
                ]);
            });
            fclose($out);
        }, 'qr-codes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<QrCode>
     */
    private function scoped(Request $request, array $filters): Builder
    {
        $ownerId = $this->workspaceOwner($request->user(), $filters['workspace'] ?? null);
        $status = $filters['status'] ?? null;

        return QrCode::query()
            ->where('user_id', $ownerId)
            ->when($status === null, fn ($q) => $q->where('status', '!=', QrStatus::Archived))
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['mode'] ?? null, fn ($q, $v) => $q->where('mode', $v))
            ->when($filters['folder_id'] ?? null, fn ($q, $v) => $q->where('folder_id', $v))
            ->when($filters['campaign_id'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($filters['search'] ?? null, function ($q, $v) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $v).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('short_code', $v));
            });
    }

    /** A user may browse a team owner's workspace only after accepting an invitation. */
    private function workspaceOwner(User $user, ?int $ownerId): int
    {
        if (! $ownerId || $ownerId === $user->id) {
            return $user->id;
        }
        $member = TeamMember::query()->where('owner_id', $ownerId)->where('user_id', $user->id)->whereNotNull('accepted_at')->exists();
        abort_unless($member, 403, 'You are not a member of this workspace.');

        return $ownerId;
    }

    private function readOnlyReason(QrCode $qr): ?string
    {
        if ($qr->moderation_status === 'blocked') {
            return 'This code was disabled by our moderation team.';
        }
        if ($qr->isDynamic() && $this->entitlements->isOverDynamicLimit($qr->user)) {
            return 'You have more dynamic codes than your plan allows. Codes keep redirecting, but editing is locked until you upgrade or archive codes.';
        }

        return null;
    }
}
