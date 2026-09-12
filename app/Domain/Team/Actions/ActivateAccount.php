<?php

namespace App\Domain\Team\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Turns an invitation into a working account: the password comes from the mailed link and the
 * status finally becomes active. The same action serves a plain reset — the only difference is
 * whether the account was active already (docs/SPEC-EKRANY.md ekran 3).
 */
class ActivateAccount
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $user, string $password): User
    {
        $wasInvited = $user->status === UserStatus::Invited;

        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
            'status' => UserStatus::Active,
        ])->save();

        $this->log->record(
            $user,
            $wasInvited ? 'Aktywował konto' : 'Ustawił nowe hasło',
            $user->email,
        );

        return $user;
    }
}
