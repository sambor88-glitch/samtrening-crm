<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Team\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']], [
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'To nie wygląda na adres e-mail.',
        ]);

        // A blocked account gets nothing: a new password would not let it in anyway.
        Password::sendResetLink([
            'email' => trim((string) $request->input('email')),
            fn (Builder $query) => $query->where('status', '!=', UserStatus::Blocked),
        ]);

        // The answer never says whether the address is in the studio — otherwise anyone could
        // check who works here. Hence no status, no errors, always the same screen
        // (docs/SPEC-EKRANY.md, ekran 2).
        return redirect()->route('password.request')->with('link_sent', true);
    }
}
