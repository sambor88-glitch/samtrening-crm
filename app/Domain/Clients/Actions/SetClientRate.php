<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Support\Money;

/**
 * The rate bar on the client card. Separate from UpdateClient because it is a one-field change
 * made in passing, and it deserves its own line in the log — both amounts included.
 */
class SetClientRate
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $actor, Client $client, int $rate): Client
    {
        $before = $client->rate;

        if ($before === $rate) {
            return $client;
        }

        $client->update(['rate' => $rate]);

        $this->log->record(
            $actor,
            'Zmienił stawkę',
            $client->name.' · '.Money::format($before).' → '.Money::format($rate),
        );

        return $client;
    }
}
