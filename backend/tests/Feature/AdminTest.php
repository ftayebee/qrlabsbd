<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_customers_cannot_reach_admin_endpoints(): void
    {
        $user = $this->customer();
        foreach (['/api/v1/admin/overview', '/api/v1/admin/customers', '/api/v1/admin/plans', '/api/v1/admin/audit-logs'] as $url) {
            $this->actingAs($user)->getJson($url)->assertForbidden();
        }
    }

    public function test_permissions_are_enforced_per_role(): void
    {
        $support = $this->admin('support');
        $this->actingAs($support)->getJson('/api/v1/admin/customers')->assertOk();
        $this->actingAs($support)->getJson('/api/v1/admin/plans')->assertForbidden();
        $this->actingAs($support)->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_admin_manages_plans_without_code_changes(): void
    {
        $admin = $this->admin();
        $res = $this->actingAs($admin)->postJson('/api/v1/admin/plans', [
            'name' => 'Agency', 'slug' => 'agency', 'status' => 'published', 'is_public' => true, 'sort_order' => 5,
            'prices' => [['interval' => 'month', 'currency' => 'eur', 'amount' => 9900, 'promo_amount' => 7900]],
            'features' => [['key' => 'dynamic_codes', 'label' => 'Dynamic codes', 'type' => 'limit', 'value' => '2000']],
        ])->assertCreated();

        $this->getJson('/api/v1/plans')->assertOk()->assertJsonFragment(['slug' => 'agency'])->assertJsonFragment(['currency' => 'EUR', 'effectiveAmount' => 7900]);

        $id = $res->json('data.id');
        $this->actingAs($admin)->deleteJson("/api/v1/admin/plans/{$id}")->assertOk();
        $this->getJson('/api/v1/plans')->assertJsonMissing(['slug' => 'agency']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.archived', 'subject_id' => $id]);
    }

    public function test_quota_override_changes_entitlements(): void
    {
        $admin = $this->admin();
        $user = $this->customer();
        $this->actingAs($admin)->postJson("/api/v1/admin/customers/{$user->id}/quota-overrides", ['key' => 'dynamic_codes', 'value' => '10', 'reason' => 'Partner'])->assertCreated();
        $this->actingAs($user)->getJson('/api/v1/account/usage')->assertJsonPath('data.entitlements.dynamic_codes', 10);
    }

    public function test_manual_grant_and_overview_use_real_data(): void
    {
        $admin = $this->admin();
        $user = $this->customer();
        $plan = \App\Models\Plan::query()->where('slug', 'business')->value('id');
        $this->actingAs($admin)->postJson("/api/v1/admin/customers/{$user->id}/grant-plan", ['plan_id' => $plan, 'reason' => 'Enterprise pilot'])->assertCreated();

        $this->actingAs($admin)->getJson('/api/v1/admin/overview')->assertOk()
            ->assertJsonPath('data.subscriptions.active', 1)
            ->assertJsonPath('data.revenue', []);
        $this->actingAs($user)->getJson('/api/v1/account/usage')->assertJsonPath('data.plan.slug', 'business');
    }
}
