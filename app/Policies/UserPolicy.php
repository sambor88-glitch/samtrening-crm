<?php

namespace App\Policies;

use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;

/**
 * Trainer accounts — docs/START-TUTAJ.md §7. Inviting, blocking and resetting someone else's
 * password belongs to the owner; everyone keeps their own card (name, BLIK number).
 */
class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->status === UserStatus::Active ? null : false;
    }

    /**
     * The trainers list lives in the admin panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_owner;
    }

    public function view(User $user, User $account): bool
    {
        return $user->is_owner || $user->is($account);
    }

    /**
     * Inviting a trainer.
     */
    public function create(User $user): bool
    {
        return $user->is_owner;
    }

    public function update(User $user, User $account): bool
    {
        return $user->is_owner || $user->is($account);
    }

    /**
     * The owner's account cannot be blocked — there would be nobody left to unblock it.
     */
    public function block(User $user, User $account): bool
    {
        return $user->is_owner && ! $account->is_owner;
    }

    /**
     * Sending a trainer a fresh link to set their password.
     */
    public function resetPassword(User $user, User $account): bool
    {
        return $user->is_owner;
    }
}
