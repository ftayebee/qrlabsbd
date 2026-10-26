<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', $response->headers->get('Referrer-Policy') ?? 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Private API data must never land in shared caches; public endpoints opt in explicitly.
        if ($request->is('api/*') && ! str_contains((string) $response->headers->get('Cache-Control'), 'public')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
