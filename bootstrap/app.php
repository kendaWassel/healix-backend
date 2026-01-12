<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;


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
    })
    ->withMiddleware(function (Middleware $middleware) {

    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);

    $middleware->alias([
        'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
    'api' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

    'role' => \App\Http\Middleware\RoleMiddleware::class,
    'active.account' => \App\Http\Middleware\EnsureAccountIsActive::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions) {
        // 
    })
    ->create();
