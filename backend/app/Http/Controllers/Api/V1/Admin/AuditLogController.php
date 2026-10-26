<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:80'],
            'subject_id' => ['nullable', 'integer'],
        ]);
        $logs = AuditLog::query()->with('actor:id,name,email')
            ->when($f['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($f['actor_id'] ?? null, fn ($q, $a) => $q->where('actor_id', $a))
            ->when($f['subject_type'] ?? null, fn ($q, $t) => $q->where('subject_type', $t))
            ->when($f['subject_id'] ?? null, fn ($q, $i) => $q->where('subject_id', $i))
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AuditLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'subjectType' => $l->subject_type, 'subjectId' => $l->subject_id,
                'reason' => $l->reason, 'changes' => $l->changes,
                'actor' => $l->actor ? ['id' => $l->actor->id, 'name' => $l->actor->name, 'email' => $l->actor->email] : null,
                'createdAt' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'per_page' => $logs->perPage(), 'total' => $logs->total()],
        ]);
    }
}
