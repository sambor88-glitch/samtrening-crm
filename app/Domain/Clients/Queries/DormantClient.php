<?php

namespace App\Domain\Clients\Queries;

use App\Domain\Clients\Models\Client;
use Carbon\CarbonImmutable;

/**
 * One client who has not trained in a while: when they last came, and how long ago that was.
 */
readonly class DormantClient
{
    public function __construct(
        public Client $client,
        public CarbonImmutable $lastOn,
        public int $days,
    ) {}
}
