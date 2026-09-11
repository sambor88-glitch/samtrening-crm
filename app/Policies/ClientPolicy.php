<?php

namespace App\Policies;

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;

/**
 * Who may touch a client card — docs/START-TUTAJ.md §7. Lists are narrowed in the query by
 * `Client::query()->forTrainer()`; this decides single records, which is what stops a swapped
 * id in the URL.
 */
class ClientPolicy
{
    /**
     * An account that is not active — invited, or blocked by the owner — gets nothing at all,
     * whatever the abilities below would say. A session opened before the block dies here too.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->status === UserStatus::Active ? null : false;
    }

    /**
     * Everyone may open their own roster; which cards are in it is the query's business.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->is_owner || $client->trainer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function archive(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }
}
