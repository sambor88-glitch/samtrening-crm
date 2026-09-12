<?php

namespace App\Domain\Team\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Password;
use RuntimeException;

/**
 * The owner cannot set somebody else's password — they send a link and the trainer chooses. Sixty
 * minutes, single use, exactly like the link from the login screen.
 */
class ResetTrainerPassword
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $owner, User $trainer): User
    {
        $status = Password::sendResetLink(['email' => $trainer->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new RuntimeException('Nie udało się wysłać linku do '.$trainer->email.'.');
        }

        $this->log->record($owner, 'Zresetował hasło trenera', $trainer->name.' · '.$trainer->email);

        return $trainer;
    }
}
