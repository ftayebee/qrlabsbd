<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessControlSeeder::class, PlanSeeder::class]);
        // Sanctum treats requests from the SPA origin as stateful.
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/']);
    }

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    protected function admin(string $role = 'super-admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->value('id'), ['created_at' => now()]);

        return $user;
    }

    /** @param array<string, mixed> $content */
    protected function qrPayload(string $type, string $mode, array $content, array $extra = []): array
    {
        return ['name' => ucfirst($type).' code', 'type' => $type, 'mode' => $mode, 'content' => $content] + $extra;
    }
}
