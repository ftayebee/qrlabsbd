<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Analytics\ScanContext;
use App\Services\Analytics\ScanRecorder;
use App\Services\Qr\QrCodeService;
use Illuminate\Database\Seeder;

/**
 * Local-only sample account (demo@example.test / password from DEMO_PASSWORD)
 * with codes and simulated scans pushed through the real ScanRecorder, so the
 * dashboard can be exercised end-to-end during development.
 */
class DemoSeeder extends Seeder
{
    public function run(QrCodeService $qr, ScanRecorder $recorder): void
    {
        if (User::query()->where('email', 'demo@example.test')->exists()) {
            return;
        }
        $user = User::query()->create(['name' => 'Demo Customer', 'email' => 'demo@example.test', 'password' => env('DEMO_PASSWORD', 'demo-password-123')]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $menu = $qr->create($user, ['name' => 'Restaurant menu', 'type' => 'url', 'mode' => 'dynamic', 'content' => ['url' => 'https://example.com/menu']]);
        $links = $qr->create($user, ['name' => 'Spring launch links', 'type' => 'multi_url', 'mode' => 'dynamic', 'content' => [
            'title' => 'Spring launch', 'description' => 'Everything about our new collection.',
            'links' => [['label' => 'Shop the collection', 'url' => 'https://example.com/shop'], ['label' => 'Lookbook', 'url' => 'https://example.com/lookbook']],
        ]]);
        $qr->create($user, ['name' => 'Guest Wi-Fi', 'type' => 'wifi', 'mode' => 'static', 'content' => ['ssid' => 'Cafe Guest', 'security' => 'WPA', 'password' => 'sample-pass-1']]);

        $devices = [['mobile', 'iOS', 'Safari'], ['mobile', 'Android', 'Chrome'], ['desktop', 'Windows', 'Edge'], ['tablet', 'iOS', 'Safari']];
        $countries = ['US', 'GB', 'DE', 'BD', 'CA', null];
        foreach ([$menu, $links] as $code) {
            for ($d = 45; $d >= 0; $d--) {
                $n = random_int(0, 12);
                for ($i = 0; $i < $n; $i++) {
                    [$device, $os, $browser] = $devices[array_rand($devices)];
                    $recorder->record(new ScanContext(
                        qrCodeId: $code->id,
                        scannedAt: now()->subDays($d)->setTime(random_int(8, 22), random_int(0, 59))->toDateTimeString(),
                        device: $device, os: $os, browser: $browser, bot: false,
                        country: $countries[array_rand($countries)], region: null,
                        referrerHost: null, visitorHash: hash('sha256', $code->id.'-'.$d.'-'.random_int(1, 40)),
                    ));
                }
            }
        }
    }
}
