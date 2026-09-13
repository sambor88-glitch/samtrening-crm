<?php

namespace App\Domain\Team\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * The link the owner hands over by hand — SC-56. Until the studio has working mail, a link sent
 * by e-mail reaches nobody, and a trainer with no way in is a trainer who cannot work.
 *
 * It is a link and not a password on purpose (docs/START-TUTAJ.md §7): the owner never learns
 * anybody else's password, so "Katarzyna wbiła sesję" in the log still means Katarzyna did it.
 *
 * Minting a new one invalidates the previous — that is how the broker works, and it is what you
 * want when a link has been passed around a messaging app.
 */
class GenerateAccessLink
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $owner, User $trainer): string
    {
        // An account that has never been used is being invited, not reset: the invitation broker
        // gives seven days and the screen greets them and asks for the studio rules.
        $invited = $trainer->status === UserStatus::Invited;

        $link = $invited
            ? route('activation.create', ['token' => Password::broker('invitations')->createToken($trainer)])
            : route('password.reset', ['token' => Password::broker()->createToken($trainer)]);

        $this->log->record(
            $owner,
            'Wygenerował link dostępu',
            $trainer->name.' · '.($invited ? 'aktywacja, ważny 7 dni' : 'reset hasła, ważny 60 minut'),
        );

        return $link.'?email='.urlencode($trainer->email);
    }
}
