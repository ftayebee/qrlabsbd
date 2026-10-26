<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Safe to re-run in any environment: it only creates missing records.
     * The first super admin is created from ADMIN_EMAIL / ADMIN_PASSWORD;
     * no default credentials are ever shipped.
     */
    public function run(): void
    {
        $this->call([AccessControlSeeder::class, PlanSeeder::class, ContentSeeder::class]);

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if ($email && $password && ! User::query()->where('email', strtolower($email))->exists()) {
            $admin = User::query()->create(['name' => 'Administrator', 'email' => strtolower($email), 'password' => $password]);
            $admin->forceFill(['email_verified_at' => now()])->save();
            $admin->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'), ['created_at' => now()]);
            $this->command?->info("Super admin created: {$admin->email}");
        } elseif (! $email) {
            $this->command?->warn('ADMIN_EMAIL/ADMIN_PASSWORD not set; no admin user created.');
        }

        // Demo data is for local development only and never runs in production.
        if (app()->environment('local') && env('SEED_DEMO_DATA')) {
            $this->call(DemoSeeder::class);
        }
    }
}
