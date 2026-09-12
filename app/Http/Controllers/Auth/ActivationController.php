<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Team\Actions\ActivateAccount;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The invitation half of screen 3. Same table of tokens as a reset, but a link that lives seven
 * days — a new trainer is not sitting by their inbox.
 */
class ActivationController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.set-password', [
            'mode' => 'invitation',
            'token' => $token,
            'email' => (string) $request->query('email'),
            'account' => $this->account($request),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, ActivateAccount $activate): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'consent' => ['accepted'],
        ], [
            'password.required' => 'Podaj hasło.',
            'password.min' => 'Hasło musi mieć minimum 8 znaków.',
            'password.confirmed' => 'Hasła się nie zgadzają.',
            'consent.accepted' => 'Zaznacz zobowiązanie do ochrony danych klientów.',
        ]);

        $status = Password::broker('invitations')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($activate, $request) {
                $activate->handle($user, $request->string('password')->value());

                Auth::login($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('toast', 'Konto aktywne. Witaj w studio.');
    }

    /**
     * The card at the top of the form — who this invitation is for.
     */
    private function account(Request $request): ?User
    {
        return User::query()
            ->where('email', (string) $request->query('email'))
            ->where('status', UserStatus::Invited)
            ->first();
    }
}
