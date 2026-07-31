<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        // 
    })
    ->create();
