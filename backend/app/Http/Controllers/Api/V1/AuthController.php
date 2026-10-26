<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** Session-based (Sanctum SPA) authentication. */
class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Returns the current user, or null for guests (200 either way, so the SPA can probe silently). */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::guard('web')->user();

        return response()->json(['data' => $user ? new UserResource($user) : null]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
        ]);
        foreach (NotificationPreference::KEYS as $key) {
            $user->notificationPreferences()->create(['channel' => 'email', 'key' => $key, 'enabled' => $key !== 'product_updates']);
        }

        event(new Registered($user));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! Auth::guard('web')->attempt(['email' => Str::lower($credentials['email']), 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();
        if ($user->isSuspended()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => 'This account has been suspended. Contact support for help.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->isAdmin()) {
            $this->audit->log($user, 'admin.login', $user);
        }

        return response()->json(['data' => new UserResource($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    /** Always returns the same response so the endpoint cannot be used to discover accounts. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => Str::lower((string) $request->input('email'))]);

        return response()->json(['message' => 'If an account exists for that email, we have sent a reset link.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => Str::lower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification link sent.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = $request->user();
        if ((string) $user->getKey() !== $id || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'This verification link is not valid for the signed-in account.');
        }
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['data' => new UserResource($user->fresh())]);
    }
}
