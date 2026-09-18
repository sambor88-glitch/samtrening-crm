<?php

use App\Http\Controllers\Api\AgentApiController;
use App\Http\Controllers\Api\AgentPaymentController;
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

/*
 * The one write the agent may make — docs/AGENT-API.md §10 and SC-66.
 *
 * A separate scope, so the reading token stays unable to move money: its leak still only
 * exposes the roster, and the writing token can be revoked on its own. A lower rate limit
 * too, because a payment is a deliberate press of a button, not a poll.
 */
Route::middleware(['agent.token:crm.write', 'throttle:30,1'])
    ->prefix('agent/v1')
    ->name('agent.')
    ->group(function () {
        Route::post('payments', [AgentPaymentController::class, 'store'])->name('payments.store');
    });
