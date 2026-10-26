<?php

namespace Tests\Feature;

use App\Models\QrCode;
use App\Models\ScanAggregate;
use App\Models\User;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private function dynamicCode(User $user, string $type = 'url', array $content = ['url' => 'https://example.com/a']): QrCode
    {
        $id = $this->actingAs($user)->postJson('/api/v1/qr-codes', $this->qrPayload($type, 'dynamic', $content))->assertCreated()->json('data.id');

        return QrCode::query()->findOrFail($id);
    }

    public function test_redirects_and_records_privacy_reduced_scan(): void
    {
        $qr = $this->dynamicCode($this->customer());

        $this->withHeaders(['User-Agent' => self::IPHONE, 'CF-IPCountry' => 'gb'])
            ->get('/r/'.$qr->short_code)
            ->assertStatus(302)
            ->assertRedirect('https://example.com/a')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $qr->refresh();
        $this->assertSame(1, $qr->total_scans);
        $this->assertSame(1, $qr->unique_scans);
        $scan = \DB::table('qr_scans')->first();
        $this->assertSame('GB', $scan->country_code);
        $this->assertSame('mobile', $scan->device_type);
        $this->assertSame('iOS', $scan->os);
        $this->assertSame(64, strlen($scan->visitor_hash));
        $this->assertStringNotContainsString('127.0.0.1', json_encode($scan));
        $this->assertSame(1, ScanAggregate::query()->where(['dimension' => 'device', 'value' => 'mobile'])->value('scans'));
    }

    public function test_repeat_scans_count_once_as_unique_and_bots_are_excluded(): void
    {
        $qr = $this->dynamicCode($this->customer());
        $this->withHeaders(['User-Agent' => self::IPHONE])->get('/r/'.$qr->short_code);
        $this->withHeaders(['User-Agent' => self::IPHONE])->get('/r/'.$qr->short_code);
        $this->withHeaders(['User-Agent' => 'facebookexternalhit/1.1'])->get('/r/'.$qr->short_code)->assertStatus(302);

        $qr->refresh();
        $this->assertSame(2, $qr->total_scans);
        $this->assertSame(1, $qr->unique_scans);
        $this->assertSame(1, ScanAggregate::query()->where('dimension', 'bot')->sum('scans'));
    }

    public function test_destination_edits_take_effect_without_reprinting(): void
    {
        $user = $this->customer();
        $qr = $this->dynamicCode($user);
        $this->get('/r/'.$qr->short_code)->assertRedirect('https://example.com/a');

        $this->actingAs($user)->patchJson("/api/v1/qr-codes/{$qr->id}", ['content' => ['url' => 'https://example.com/b']])->assertOk();
        $this->get('/r/'.$qr->short_code)->assertRedirect('https://example.com/b');
    }

    public function test_paused_archived_blocked_and_missing_codes_do_not_redirect(): void
    {
        $user = $this->customer();
        $qr = $this->dynamicCode($user);

        $this->actingAs($user)->patchJson("/api/v1/qr-codes/{$qr->id}/status", ['status' => 'paused']);
        $this->get('/r/'.$qr->short_code)->assertOk()->assertSee('Temporarily paused');

        $this->actingAs($user)->patchJson("/api/v1/qr-codes/{$qr->id}/status", ['status' => 'archived']);
        $this->get('/r/'.$qr->short_code)->assertStatus(410);

        $this->get('/r/doesNotExist1')->assertNotFound();
        $this->assertSame(0, $qr->fresh()->total_scans);
    }

    public function test_hosted_types_redirect_to_landing_page_and_app_routes_by_device(): void
    {
        $user = $this->customer();
        $multi = $this->dynamicCode($user, 'multi_url', ['title' => 'Links', 'links' => [['label' => 'Shop', 'url' => 'https://example.com/shop']]]);
        $this->get('/r/'.$multi->short_code)->assertRedirect('https://app.qrio.test/p/'.$multi->short_code);
        $this->getJson('/api/v1/public/qr/'.$multi->short_code)->assertOk()->assertJsonPath('data.content.links.0.url', 'https://example.com/shop');

        $app = $this->dynamicCode($user, 'app', ['name' => 'App', 'iosUrl' => 'https://apps.apple.com/app/id1', 'androidUrl' => 'https://play.google.com/store/apps/details?id=x']);
        $this->withHeaders(['User-Agent' => self::IPHONE])->get('/r/'.$app->short_code)->assertRedirect('https://apps.apple.com/app/id1');
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0)'])->get('/r/'.$app->short_code)->assertRedirect('https://app.qrio.test/p/'.$app->short_code);
    }

    public function test_suspended_owner_codes_stop_resolving(): void
    {
        $user = $this->customer();
        $qr = $this->dynamicCode($user);
        $admin = $this->admin();
        $this->actingAs($admin)->patchJson("/api/v1/admin/customers/{$user->id}", ['status' => 'suspended', 'reason' => 'Phishing'])->assertOk();

        $this->get('/r/'.$qr->short_code)->assertStatus(410);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.status_changed', 'subject_id' => $user->id, 'reason' => 'Phishing']);
    }
}
