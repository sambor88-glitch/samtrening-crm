<?php

namespace App\Policies;

use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;

/**
 * Sessions inherit their access from the client they belong to — docs/START-TUTAJ.md §7.
 * Files and exports must ask the same way once they exist (SC-33, SC-45).
 */
class TrainingSessionPolicy
{
    public function __construct(private readonly ClientPolicy $clients) {}

    public function before(User $user, string $ability): ?bool
    {
        return $user->status === UserStatus::Active ? null : false;
    }

    public function view(User $user, TrainingSession $session): bool
    {
        return $this->clients->view($user, $session->client);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TrainingSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function delete(User $user, TrainingSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function restore(User $user, TrainingSession $session): bool
    {
        return $this->view($user, $session);
    }
}
