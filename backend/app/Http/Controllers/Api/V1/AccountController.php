<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\UserResource;
use App\Models\NotificationPreference;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\SubscriptionService;
use App\Services\Qr\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $e = $this->entitlements->for($user);
        $subscription = $e->subscription?->load(['plan', 'price']);

        return response()->json(['data' => [
            'plan' => ['id' => $e->plan->id, 'name' => $e->plan->name, 'slug' => $e->plan->slug],
            'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
            'entitlements' => $e->toArray(),
            'usage' => $this->entitlements->usage($user),
            'readOnly' => $this->entitlements->isOverDynamicLimit($user),
            'inGrace' => $e->inGrace,
            'graceEndsAt' => $e->graceEndsAt?->toIso8601String(),
        ]]);
    }

    public function updateProfile(Request $request): UserResource
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email:rfc', 'max:190', Rule::unique('users')->ignore($user->id)],
            'timezone' => ['sometimes', 'timezone:all'],
            'current_password' => ['required_with:email', 'current_password:web'],
        ]);
        unset($data['current_password']);

        if (isset($data['email']) && strtolower($data['email']) !== $user->email) {
            $data['email'] = strtolower($data['email']);
            $user->forceFill(['email_verified_at' => null]);
            $user->fill($data)->save();
            $user->sendEmailVerificationNotification();
        } else {
            $user->fill($data)->save();
        }

        return new UserResource($user);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->update(['password' => $data['password']]);
        // Sign out other browsers that still hold the old password.
        Auth::guard('web')->logoutOtherDevices($data['password']);

        return response()->json(['message' => 'Password updated.']);
    }

    public function notifications(Request $request): JsonResponse
    {
        $prefs = $request->user()->notificationPreferences()->pluck('enabled', 'key');

        return response()->json(['data' => collect(NotificationPreference::KEYS)->mapWithKeys(fn ($k) => [$k => (bool) ($prefs[$k] ?? true)])]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        $data = $request->validate(collect(NotificationPreference::KEYS)->mapWithKeys(fn ($k) => [$k => ['sometimes', 'boolean']])->all());
        foreach ($data as $key => $enabled) {
            $request->user()->notificationPreferences()->updateOrCreate(['channel' => 'email', 'key' => $key], ['enabled' => $enabled]);
        }

        return $this->notifications($request);
    }

    /** Closes the account. Active paid subscriptions must be cancelled first so billing stops cleanly. */
    public function destroy(Request $request, SubscriptionService $subscriptions, QrCodeService $qrCodes): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $user = $request->user();
        $current = $subscriptions->currentPaid($user);
        if ($current && ! $current->is_manual && ! $current->cancel_at_period_end) {
            throw ValidationException::withMessages(['password' => 'Cancel your subscription before closing your account.']);
        }

        // Soft-deleting keeps short codes reserved, so printed codes can never be reassigned.
        $user->qrCodes()->get()->each(fn ($qr) => $qrCodes->delete($qr));
        $user->tokens()->delete();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $user->delete();

        return response()->json(['message' => 'Your account has been closed.']);
    }
}
