<?php

namespace App\Domain\Clients\Queries;

use Illuminate\Support\Collection;

/**
 * The rows the trainer can see right now, and how many the filter had to choose from —
 * that pair is the "X z Y" counter above the table.
 */
readonly class Roster
{
    /**
     * @param  Collection<int, RosterRow>  $rows
     */
    public function __construct(
        public Collection $rows,
        public int $total,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows->isEmpty();
    }
}
