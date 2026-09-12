<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Support\Money;
use RuntimeException;

/**
 * Archiving takes a client out of the rosters, the statistics and the arrears — which is exactly
 * why an unpaid balance blocks it (docs/SPEC-EKRANY.md, reguła biznesowa 1). The rule lives here
 * and not in a greyed-out button, so calling the action directly is refused too.
 *
 * Their past sessions keep counting towards earnings: that work was done and paid for.
 */
class ArchiveClient
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly Balance $balance,
    ) {}

    public function handle(User $actor, Client $client, bool $archived = true): Client
    {
        $owed = $this->balance->forClient($client);

        if ($archived && $owed > 0) {
            throw new RuntimeException('Najpierw rozlicz saldo — '.Money::format($owed).' nie może zniknąć z widoku.');
        }

        if ($client->archived === $archived) {
            return $client;
        }

        $client->update(['archived' => $archived]);

        $this->log->record(
            $actor,
            $archived ? 'Zarchiwizował klienta' : 'Przywrócił klienta z archiwum',
            $client->name,
        );

        return $client;
    }
}
