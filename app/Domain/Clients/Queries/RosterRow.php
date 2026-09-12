<?php

namespace App\Domain\Clients\Queries;

use App\Domain\Clients\Models\Client;
use Carbon\CarbonImmutable;

/**
 * One line of the client list: the card, what it owes and when it was last trained.
 */
readonly class RosterRow
{
    public function __construct(
        public Client $client,
        public int $balance,
        public ?CarbonImmutable $lastSessionOn,
    ) {}

    public function owes(): bool
    {
        return $this->balance > 0;
    }
}
