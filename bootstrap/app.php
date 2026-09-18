<?php

use App\Http\Middleware\AgentToken;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsOwner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // The read-only agent API, served under /api — docs/AGENT-API.md. Stateless on purpose:
        // these routes never get the session or CSRF middleware the panel runs on.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Without this, "wylogowaliśmy pozostałe urządzenia" would be a promise nobody keeps:
        // Auth::logoutOtherDevices() only bites when sessions carry the password hash.
        $middleware->web(append: [AuthenticateSession::class]);

        $middleware->alias([
            'owner' => EnsureUserIsOwner::class,
            'active' => EnsureUserIsActive::class,
            'agent.token' => AgentToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
