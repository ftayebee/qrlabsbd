<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\TeamInviteNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Team members can browse (viewer) or edit (editor) the owner's QR codes. Gated by the team_members entitlement. */
class TeamController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $members = TeamMember::query()->where('owner_id', $user->id)->with('user:id,name,email')->latest()->get();
        $workspaces = TeamMember::query()->where('user_id', $user->id)->whereNotNull('accepted_at')->with('owner:id,name')->get();

        return response()->json(['data' => [
            'limit' => $this->entitlements->for($user)->limit('team_members'),
            'members' => $members->map(fn (TeamMember $m) => [
                'id' => $m->id, 'email' => $m->email, 'name' => $m->user?->name, 'role' => $m->role,
                'acceptedAt' => $m->accepted_at?->toIso8601String(), 'invitedAt' => $m->created_at?->toIso8601String(),
            ]),
            'workspaces' => $workspaces->map(fn (TeamMember $m) => ['ownerId' => $m->owner_id, 'ownerName' => $m->owner?->name, 'role' => $m->role]),
        ]]);
    }

    public function invite(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::notIn([$user->email]), Rule::unique('team_members')->where('owner_id', $user->id)],
            'role' => ['required', Rule::in(['viewer', 'editor'])],
        ]);
        $e = $this->entitlements->for($user);
        $count = TeamMember::query()->where('owner_id', $user->id)->count();
        if (! $e->withinLimit('team_members', $count)) {
            throw new PlanLimitException('team_members', 'Your plan does not include more team seats.');
        }

        $token = Str::random(48);
        $member = TeamMember::query()->create(['owner_id' => $user->id, 'email' => strtolower($data['email']), 'role' => $data['role'], 'token' => hash('sha256', $token)]);
        Notification::route('mail', $member->email)->notify(new TeamInviteNotification($member->load('owner'), $token));

        return response()->json(['data' => ['id' => $member->id, 'email' => $member->email, 'role' => $member->role]], 201);
    }

    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48']]);
        /** @var User $user */
        $user = $request->user();
        $member = TeamMember::query()->where('token', hash('sha256', $data['token']))->first();
        abort_unless($member && strtolower($member->email) === strtolower($user->email), 404, 'This invitation is not valid for your account.');

        $member->update(['user_id' => $user->id, 'accepted_at' => now(), 'token' => null]);

        return response()->json(['message' => 'Invitation accepted.', 'data' => ['ownerId' => $member->owner_id]]);
    }

    public function destroy(Request $request, TeamMember $member): JsonResponse
    {
        $user = $request->user();
        // Owners can remove members; members can leave a workspace.
        abort_unless($member->owner_id === $user->id || $member->user_id === $user->id, 404);
        $member->delete();

        return response()->json(['message' => 'Removed.']);
    }
}
