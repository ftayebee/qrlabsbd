<?php

namespace Tests\Feature;

use App\Models\QrCode;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    public function test_guest_cannot_create_codes(): void
    {
        $this->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'https://example.com']))
            ->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_dynamic_url_code_gets_short_link_and_destination(): void
    {
        $user = $this->customer();
        $res = $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'example.com/menu']))
            ->assertCreated()
            ->assertJsonPath('data.mode', 'dynamic');

        $shortCode = $res->json('data.shortCode');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{7}$/', $shortCode);
        $this->assertSame('https://qr.qrio.test/r/'.$shortCode, $res->json('data.encodedValue'));
        $this->assertDatabaseHas('qr_destinations', ['kind' => 'primary', 'url' => 'https://example.com/menu']);
    }

    public function test_static_wifi_payload_is_derived_server_side_and_encrypted_at_rest(): void
    {
        $user = $this->customer();
        $res = $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('wifi', 'static', ['ssid' => 'Cafe;1', 'security' => 'WPA', 'password' => 'secret-pass']))
            ->assertCreated();
        $this->assertSame('WIFI:T:WPA;S:Cafe\;1;P:secret-pass;;', $res->json('data.encodedValue'));

        $raw = \DB::table('qr_codes')->where('id', $res->json('data.id'))->value('content');
        $this->assertStringNotContainsString('secret-pass', $raw);

        // Lists never include the Wi-Fi password.
        $this->getJson('/api/v1/qr-codes')->assertOk()->assertJsonMissingPath('data.0.content.password')->assertJsonPath('data.0.encodedValue', null);
    }

    public function test_unsafe_destinations_are_rejected(): void
    {
        $user = $this->customer();
        foreach (['javascript:alert(1)', 'http://localhost/admin', 'http://192.168.1.1', 'https://user:pass@example.com', 'https://qr.qrio.test/r/abc1234'] as $url) {
            $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => $url]))
                ->assertUnprocessable()->assertJsonValidationErrors('content.url');
        }
    }

    public function test_mode_must_be_supported_by_type(): void
    {
        $this->actingAs($this->customer())->postJson('/api/v1/qr-codes', $this->qrPayload('wifi', 'dynamic', ['ssid' => 'x', 'security' => 'nopass']))
            ->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    public function test_free_plan_dynamic_limit_is_enforced_server_side(): void
    {
        $user = $this->customer();
        foreach ([1, 2] as $_) {
            $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'https://example.com']))->assertCreated();
        }
        $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'https://example.com']))
            ->assertStatus(402)->assertJsonPath('code', 'plan_limit')->assertJsonPath('feature', 'dynamic_codes');
    }

    public function test_customers_cannot_access_each_others_codes(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $id = $this->actingAs($owner)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'https://example.com']))->json('data.id');

        $this->actingAs($other)->getJson("/api/v1/qr-codes/{$id}")->assertForbidden();
        $this->actingAs($other)->patchJson("/api/v1/qr-codes/{$id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/v1/qr-codes/{$id}")->assertForbidden();
        $this->actingAs($other)->getJson("/api/v1/analytics/qr-codes/{$id}")->assertForbidden();
        $this->actingAs($other)->getJson('/api/v1/qr-codes')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($other)->postJson('/api/v1/qr-codes/bulk', ['ids' => [$id], 'action' => 'delete'])->assertOk()->assertJsonPath('count', 0);
        $this->assertDatabaseHas('qr_codes', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_owner_can_edit_duplicate_and_archive(): void
    {
        $user = $this->customer();
        $id = $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('url', 'dynamic', ['url' => 'https://example.com']))->json('data.id');

        $this->patchJson("/api/v1/qr-codes/{$id}", ['content' => ['url' => 'https://example.org/new']])->assertOk();
        $this->assertDatabaseHas('qr_destinations', ['qr_code_id' => $id, 'url' => 'https://example.org/new']);
        $this->getJson("/api/v1/qr-codes/{$id}/history")->assertOk()->assertJsonPath('data.0.summary', 'Destination updated');

        $copy = $this->postJson("/api/v1/qr-codes/{$id}/duplicate")->assertCreated();
        $this->assertNotSame($copy->json('data.shortCode'), QrCode::query()->find($id)->short_code);

        $this->patchJson("/api/v1/qr-codes/{$id}/status", ['status' => 'archived'])->assertOk()->assertJsonPath('data.status', 'archived');
    }

    public function test_extra_content_keys_are_discarded(): void
    {
        $user = $this->customer();
        $id = $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload('text', 'static', ['text' => 'Hello', 'evil' => '<script>']))->json('data.id');
        $this->assertSame(['text' => 'Hello'], QrCode::query()->find($id)->content);
    }
}
