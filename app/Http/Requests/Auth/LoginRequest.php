<?php

namespace App\Http\Requests\Auth;

use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * The account behind the address typed in, looked up once.
     */
    protected ?User $account = null;

    protected bool $accountLoaded = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Each message says what to do next — docs/SPEC-EKRANY.md, ekran 1.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'To nie wygląda na adres e-mail.',
            'password.required' => 'Podaj hasło.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($email = $this->input('email'))) {
            $this->merge(['email' => trim($email)]);
        }
    }

    /**
     * The account for the address typed in, if the studio has one. Only safe to call once
     * validation has run — before that the input is not guaranteed to be a string.
     */
    public function account(): ?User
    {
        if (! $this->accountLoaded) {
            $this->account = User::query()->where('email', $this->input('email'))->first();
            $this->accountLoaded = true;
        }

        return $this->account;
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Accounts come from the owner, so an unknown or blocked address is told outright.
        // A neutral "wrong credentials" would only send people to a reset that cannot help.
        if (! $account = $this->account()) {
            $this->refuse('Nie znamy tego adresu. Reset hasła tu nie pomoże — konto musi założyć właściciel studia.');
        }

        if ($account->status === UserStatus::Blocked) {
            $this->refuse('Konto zablokowane. Reset hasła tego nie zmieni — odblokować może tylko właściciel studia.');
        }

        if (! Auth::attempt($this->only('email', 'password'))) {
            $this->refuse(trans('auth.failed'));
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    /**
     * Every refused attempt counts towards the limit, whatever the reason.
     *
     * @throws ValidationException
     */
    protected function refuse(string $message): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages(['email' => $message]);
    }
}
