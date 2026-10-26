<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'permission' => EnsurePermission::class,
            'active' => EnsureAccountActive::class,
        ]);
        $middleware->validateCsrfTokens(except: ['api/v1/webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never echo request input (passwords, Wi-Fi keys) back in logs.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'content']);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // One consistent error envelope for the API: { message, code, errors? }.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => response()->json(['message' => $e->getMessage(), 'code' => 'validation_failed', 'errors' => $e->errors()], 422),
                $e instanceof AuthenticationException => response()->json(['message' => 'You need to sign in to continue.', 'code' => 'unauthenticated'], 401),
                $e instanceof AuthorizationException => response()->json(['message' => $e->getMessage() ?: 'You are not allowed to do that.', 'code' => 'forbidden'], 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => response()->json(['message' => 'Not found.', 'code' => 'not_found'], 404),
                $e instanceof ThrottleRequestsException => response()->json(['message' => 'Too many requests. Please slow down.', 'code' => 'rate_limited'], 429, $e->getHeaders()),
                $e instanceof HttpExceptionInterface => response()->json(['message' => $e->getMessage() ?: 'Request failed.', 'code' => 'http_'.$e->getStatusCode()], $e->getStatusCode(), $e->getHeaders()),
                default => null,
            };
        });
    })->create();
