<?php

use App\Http\Controllers\FileServeController;
use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

// Dynamic QR redirect. Kept outside /api and free of session middleware for speed.
Route::get('r/{code}', RedirectController::class)
    ->middleware('throttle:redirects')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ])
    ->where('code', '[A-Za-z0-9]{4,16}');

// Public, controlled access to hosted PDFs and logos by opaque token.
Route::get('f/{token}', FileServeController::class)
    ->middleware('throttle:redirects')
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->where('token', '[A-Za-z0-9]{32}');

Route::get('/', fn () => response()->json(['name' => config('app.name').' API', 'docs' => '/docs/openapi.yaml']));
