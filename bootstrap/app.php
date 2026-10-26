<?php

use App\Http\Middleware\CheckConnection;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            CheckConnection::class,
        ]);

        // The device timezone cookie is written by JavaScript, so it is not encrypted.
        $middleware->encryptCookies(except: ['tz']);

        // Stripe webhooks and Apple's sign-in callback are posted by those services.
        $middleware->validateCsrfTokens(except: ['stripe/*', 'auth/apple/callback']);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'profile.complete' => EnsureProfileIsComplete::class,
            'onboarded' => EnsureOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
