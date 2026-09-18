<?php

use App\Http\Controllers\Api\AgentApiController;
use Illuminate\Support\Facades\Route;

/*
 * The read-only agent API — docs/AGENT-API.md.
 *
 * The caller is a script, not a browser: a bearer token, no cookies, no session, no CSRF. The
 * token is checked against `api_tokens` and must carry the scope named here; it opens this group
 * of routes and nothing else in the application.
 *
 * Sixty calls a minute is far above what an hourly refresh needs, and low enough that a leaked
 * token cannot be used to walk the whole studio quickly.
 */
Route::middleware(['agent.token:crm.read', 'throttle:60,1'])
    ->prefix('agent/v1')
    ->name('agent.')
    ->group(function () {
        Route::get('clients', [AgentApiController::class, 'clients'])->name('clients');
        Route::get('summary', [AgentApiController::class, 'summary'])->name('summary');
    });
