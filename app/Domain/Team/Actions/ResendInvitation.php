<?php

namespace App\Domain\Team\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Domain\Team\Notifications\TrainerInvitation;
use Illuminate\Support\Facades\Password;
use RuntimeException;

/**
 * A fresh link for somebody who lost theirs. The new token replaces the old row in the token
 * table, so the previous link stops working the moment this one is sent.
 */
class ResendInvitation
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $owner, User $trainer): User
    {
        if ($trainer->status !== UserStatus::Invited) {
            throw new RuntimeException('To konto jest już aktywne — zaproszenia się nie ponawia.');
        }

        $trainer->notify(new TrainerInvitation(
            Password::broker('invitations')->createToken($trainer),
            $owner->name,
        ));

        $this->log->record($owner, 'Ponowił zaproszenie', $trainer->name.' · '.$trainer->email);

        return $trainer;
    }
}
