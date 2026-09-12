<?php

namespace App\Domain\Team\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Domain\Team\Notifications\TrainerInvitation;
use Illuminate\Support\Facades\Password;

/**
 * Accounts exist because the owner made them — there is no registration anywhere in this product.
 * The new account has no password and sees nothing until the invitation is used.
 */
class InviteTrainer
{
    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @param  array<string, string|null>  $attributes  name, email, specialty
     */
    public function handle(User $owner, array $attributes): User
    {
        $trainer = new User([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'specialty' => $attributes['specialty'] ?? null,
        ]);

        $trainer->forceFill([
            'password' => null,
            'status' => UserStatus::Invited,
            'is_owner' => false,
        ])->save();

        $trainer->notify(new TrainerInvitation(
            Password::broker('invitations')->createToken($trainer),
            $owner->name,
        ));

        $this->log->record($owner, 'Zaprosił trenera', $trainer->name.' · '.$trainer->email);

        return $trainer;
    }
}
