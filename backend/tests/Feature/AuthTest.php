<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_register_login_logout_flow(): void
    {
        Notification::fake();
        // Act as the SPA: Sanctum only starts a session for requests from a stateful frontend.
        $this->withHeader('Referer', config('app.frontend_url').'/register');
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sam', 'email' => 'Sam@Example.com', 'password' => 'correct-horse-9', 'password_confirmation' => 'correct-horse-9', 'terms' => true,
        ])->assertCreated()->assertJsonPath('data.email', 'sam@example.com')->assertJsonPath('data.emailVerified', false);

        $user = User::query()->where('email', 'sam@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/v1/auth/login', ['email' => 'sam@example.com', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => 'sam@example.com', 'password' => 'correct-horse-9'])->assertOk();
    }

    public function test_suspended_accounts_cannot_sign_in(): void
    {
        $user = $this->customer(['status' => 'suspended', 'password' => 'correct-horse-9']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-horse-9'])->assertUnprocessable();
    }

    public function test_forgot_password_does_not_reveal_accounts(): void
    {
        $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');
        $user = $this->customer();
        $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json('message');
        $this->assertSame($a, $b);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'nope']);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.com', 'password' => 'nope'])->assertStatus(429);
    }
}
