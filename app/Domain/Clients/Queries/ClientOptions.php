<?php

namespace App\Domain\Clients\Queries;

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;

/**
 * The client picker in the "log a session" dialog: active cards only, by name.
 */
class ClientOptions
{
    /**
     * @return array<int, string>
     */
    public function forTrainer(User $trainer): array
    {
        return Client::query()
            ->forTrainer($trainer)
            ->where('archived', false)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
