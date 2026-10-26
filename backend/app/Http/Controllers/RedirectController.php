<?php

namespace App\Http\Controllers;

use App\Jobs\RecordScan;
use App\Services\Analytics\ScanContext;
use App\Services\Analytics\UserAgentParser;
use App\Services\Qr\RedirectResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class RedirectController extends Controller
{
    public function __invoke(Request $request, string $code, RedirectResolver $resolver, UserAgentParser $parser): BaseResponse
    {
        $entry = $resolver->lookup($code);
        if (! $entry) {
            return $this->statusPage(404, 'QR code not found', 'This code does not exist or has been removed.');
        }

        $target = $resolver->target($entry, $code, $request->userAgent());
        if (! $target) {
            return match (true) {
                $entry['blocked'] => $this->statusPage(410, 'QR code disabled', 'This code has been disabled because it violated our acceptable use policy.'),
                $entry['status'] === 'paused' => $this->statusPage(200, 'Temporarily paused', 'The owner has paused this QR code. Please check back later.'),
                default => $this->statusPage(410, 'QR code no longer active', 'The owner has deactivated this QR code.'),
            };
        }

        // Build the privacy-reduced context now; the raw IP never reaches the queue.
        // Queued (Redis in production) so the redirect never waits on analytics writes.
        RecordScan::dispatch(ScanContext::fromRequest($request, $entry['id'], $parser));

        // 302 with no-store so browsers never cache a destination that the owner may change.
        return redirect()->away($target, 302)->withHeaders([
            'Cache-Control' => 'no-store, max-age=0',
            'Referrer-Policy' => 'no-referrer-when-downgrade',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function statusPage(int $status, string $title, string $message): Response
    {
        return response()->view('redirect-status', [
            'title' => $title,
            'message' => $message,
            'home' => config('app.frontend_url'),
            'brand' => config('app.name'),
        ], $status)->header('X-Robots-Tag', 'noindex');
    }
}
