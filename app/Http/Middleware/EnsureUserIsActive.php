<?php

namespace App\Http\Middleware;

use App\Domain\Team\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A blocked account stops working on its next request, whatever the session driver keeps. The
 * message is the one from the login screen, so the trainer reads the same sentence twice rather
 * than guessing.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->status !== UserStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Konto zablokowane. Reset hasła tego nie zmieni — odblokować może tylko właściciel studia.',
            ]);
        }

        return $next($request);
    }
}
