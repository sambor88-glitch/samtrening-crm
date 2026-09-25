<?php

use App\Mcp\Servers\StudioServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
 * Claude's connector — docs/CLAUDE-CONNECTOR.md, SC-68.
 *
 * The discovery documents and client registration claude.ai walks through before the consent
 * screen. Registration is open to anyone by design (RFC 7591) and writes a row each time, hence
 * the limit; the redirect it accepts can only point back at claude.ai (config/mcp.php).
 */
Route::middleware('throttle:20,1')->group(fn () => Mcp::oauthRoutes());

/*
 * The server itself. A Passport token with the connector's scope, belonging to the studio owner's
 * active account — checked on every call, not only when the connector was set up.
 */
Mcp::web('/mcp', StudioServer::class)->middleware([
    'auth:api',
    CheckToken::using(Registrar::OAUTH_SCOPE),
    'connector.owner',
    'throttle:mcp',
]);
