<?php

namespace App\Http\Middleware;

use App\Domain\Team\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Claude's connector answers to the studio owner and nobody else — SC-68. The consent screen
 * already refuses a trainer, but a token is checked here on every call as well: the screen is a
 * courtesy, this is the lock. A blocked owner account stops working on its next call.
 *
 * Only a bearer token from the OAuth flow counts. Passport also lets a logged-in panel session
 * through its `laravel_token` cookie (/oauth/token/refresh mints one), and that "transient" token
 * passes every scope check — so any script running in the panel would reach /mcp without the
 * consent screen ever being shown.
 */
class EnsureConnectorOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->token() instanceof AccessToken, 401);

        abort_unless($user->is_owner && $user->status === UserStatus::Active, 403, 'Połączenie z Claude jest tylko dla właściciela studia.');

        return $next($request);
    }
}
