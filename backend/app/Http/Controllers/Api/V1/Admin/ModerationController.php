<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbuseReport;
use App\Models\QrCode;
use App\Models\QrFile;
use App\Services\AuditLogger;
use App\Services\Qr\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ModerationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly QrCodeService $qr) {}

    /** Platform-wide QR search. Content is not returned here; admins see metadata and destinations only. */
    public function qrCodes(Request $request): JsonResponse
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'moderation' => ['nullable', Rule::in(['clear', 'flagged', 'blocked'])],
            'type' => ['nullable', 'string', 'max:20'],
        ]);
        $codes = QrCode::query()->with(['user:id,name,email', 'destinations:id,qr_code_id,kind,url'])
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('short_code', $s)->orWhere('name', 'like', "%{$s}%")
                ->orWhereHas('destinations', fn ($d) => $d->where('url', 'like', "%{$s}%"))))
            ->when($f['moderation'] ?? null, fn ($q, $m) => $q->where('moderation_status', $m))
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->withCount(['abuseReports as open_reports' => fn ($q) => $q->where('status', 'open')])
            ->latest()->paginate(25)->withQueryString();

        return response()->json([
            'data' => $codes->getCollection()->map(fn (QrCode $c) => [
                'id' => $c->id, 'name' => $c->name, 'type' => $c->type->value, 'mode' => $c->mode->value, 'status' => $c->status->value,
                'shortUrl' => $c->shortUrl(), 'moderationStatus' => $c->moderation_status, 'totalScans' => $c->total_scans,
                'openReports' => (int) $c->open_reports,
                'destinations' => $c->destinations->map(fn ($d) => ['kind' => $d->kind, 'url' => $d->url]),
                'owner' => ['id' => $c->user->id, 'name' => $c->user->name, 'email' => $c->user->email],
                'createdAt' => $c->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $codes->currentPage(), 'last_page' => $codes->lastPage(), 'per_page' => $codes->perPage(), 'total' => $codes->total()],
        ]);
    }

    public function moderate(Request $request, QrCode $qrCode): JsonResponse
    {
        $data = $request->validate(['moderation_status' => ['required', Rule::in(['clear', 'flagged', 'blocked'])], 'reason' => ['required', 'string', 'max:500']]);
        $from = $qrCode->moderation_status;
        $qrCode->update(['moderation_status' => $data['moderation_status']]);
        $this->qr->forgetCache($qrCode);
        $this->audit->log($request->user(), 'qr.moderated', $qrCode, $data['reason'], ['from' => $from, 'to' => $data['moderation_status']]);

        return response()->json(['message' => 'Moderation status updated.']);
    }

    public function reports(Request $request): JsonResponse
    {
        $reports = AbuseReport::query()->with('qrCode:id,name,short_code,moderation_status,user_id')
            ->when($request->query('status', 'open'), fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
            ->latest()->paginate(25);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (AbuseReport $r) => [
                'id' => $r->id, 'reason' => $r->reason, 'details' => $r->details, 'status' => $r->status,
                'reporterEmail' => $r->reporter_email, 'resolutionNote' => $r->resolution_note,
                'qr' => $r->qrCode ? ['id' => $r->qrCode->id, 'name' => $r->qrCode->name, 'shortUrl' => $r->qrCode->shortUrl(), 'moderationStatus' => $r->qrCode->moderation_status] : null,
                'createdAt' => $r->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $reports->currentPage(), 'last_page' => $reports->lastPage(), 'per_page' => $reports->perPage(), 'total' => $reports->total()],
        ]);
    }

    public function resolve(Request $request, AbuseReport $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['resolved', 'dismissed'])],
            'resolution_note' => ['required', 'string', 'max:500'],
            'block_code' => ['boolean'],
        ]);
        $report->update(['status' => $data['status'], 'resolution_note' => $data['resolution_note'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        if (($data['block_code'] ?? false) && $report->qrCode) {
            $report->qrCode->update(['moderation_status' => 'blocked']);
            $this->qr->forgetCache($report->qrCode);
        }
        $this->audit->log($request->user(), 'abuse_report.'.$data['status'], $report, $data['resolution_note'], ['blocked' => (bool) ($data['block_code'] ?? false)]);

        return response()->json(['message' => 'Report updated.']);
    }

    public function storage(): JsonResponse
    {
        $byPurpose = QrFile::query()->groupBy('purpose')->get(['purpose', DB::raw('COUNT(*) as files'), DB::raw('SUM(size) as bytes')]);
        $topUsers = QrFile::query()->join('users', 'users.id', '=', 'qr_files.user_id')->whereNull('qr_files.deleted_at')
            ->groupBy('users.id', 'users.email')->orderByDesc(DB::raw('SUM(qr_files.size)'))->limit(20)
            ->get(['users.id', 'users.email', DB::raw('COUNT(*) as files'), DB::raw('SUM(qr_files.size) as bytes')]);

        return response()->json(['data' => [
            'totalBytes' => (int) $byPurpose->sum('bytes'),
            'totalFiles' => (int) $byPurpose->sum('files'),
            'byPurpose' => $byPurpose->map(fn ($r) => ['purpose' => $r->purpose, 'files' => (int) $r->files, 'bytes' => (int) $r->bytes]),
            'topUsers' => $topUsers->map(fn ($r) => ['id' => $r->id, 'email' => $r->email, 'files' => (int) $r->files, 'bytes' => (int) $r->bytes]),
            'disk' => config('qrio.files_disk'),
        ]]);
    }
}
