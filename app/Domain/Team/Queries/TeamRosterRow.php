<?php

namespace App\Domain\Team\Queries;

use App\Domain\Team\Models\User;

/**
 * One line of the studio's team list: who they are and what they brought in.
 */
readonly class TeamRosterRow
{
    public function __construct(
        public User $trainer,
        public int $clients,
        public int $sessions,
        public int $revenue,
    ) {}
}
