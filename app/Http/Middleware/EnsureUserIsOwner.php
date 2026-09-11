<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel belongs to the studio owner. Everyone else gets 403 — enforced here on the
 * server, not by hiding links.
 */
class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_owner, 403);

        return $next($request);
    }
}
