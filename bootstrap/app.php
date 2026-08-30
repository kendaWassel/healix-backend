<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\EncodeJsonUnescaped;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifiedEmail;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up'
    )
    ->withSchedule(function ($schedule) {
        $schedule->command('consultations:send-reminders')->everyMinute();
        $schedule->command('password-otp:prune')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware) {

    // Never resolve route('login') for an API request. Laravel's default
    // Authenticate middleware computes redirectTo() EAGERLY (before the
    // AuthenticationException is even constructed) whenever the request
    // doesn't send Accept: application/json — and this app has no named
    // 'login' route (API-only), so that route() call itself threw
    // RouteNotFoundException, surfacing as a raw 500 instead of the 401
    // the ->withExceptions() render() callback below is meant to produce.
    // Returning null here for any api/* path skips the redirect attempt
    // entirely, regardless of the request's Accept header.
    $middleware->redirectGuestsTo(fn ($request) => $request->is('api/*') ? null : route('login'));

    // The app runs behind an ngrok tunnel in dev: the actual TCP connection to
    // Laravel is plain HTTP on localhost, and ngrok describes the real public
    // HTTPS request via X-Forwarded-* headers. Without trusting those headers,
    // Laravel validates signed URLs (e.g. the email-verification link) against
    // the wrong scheme/host and rejects them as "Invalid signature."
    $middleware->trustProxies(at: '*');

    // SetLocale runs before anything that can emit a user-facing string, so
    // validation errors and auth failures are already translated by the time
    // they are rendered.
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
        SetLocale::class,
        EncodeJsonUnescaped::class,
    ]);

    $middleware->web(prepend: [
        SetLocale::class,
    ]);

    $middleware->alias([
        'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'api' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'role' => RoleMiddleware::class,
        'verified' => VerifiedEmail::class,
        'active.account' => \App\Http\Middleware\EnsureAccountIsActive::class,
        'locale' => SetLocale::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions) {
        // API-only app: there is no named 'login' route for the default
        // Authenticate middleware to redirect unauthenticated requests to.
        // Without this, any request under /api/* that arrives without
        // Accept: application/json (a raw curl/Postman call, a missing or
        // expired Bearer token from a client that didn't set that header)
        // hits Laravel's default redirectTo(route('login')), which throws
        // RouteNotFoundException and surfaces as a misleading 500 instead
        // of the real 401.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('auth.unauthenticated'),
                ], 401);
            }
        });
    })
    ->create();
