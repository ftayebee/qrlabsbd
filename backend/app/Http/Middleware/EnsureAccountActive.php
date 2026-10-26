<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            return response()->json(['message' => 'This account has been suspended. Contact support for help.', 'code' => 'account_suspended'], 403);
        }

        return $next($request);
    }
}
