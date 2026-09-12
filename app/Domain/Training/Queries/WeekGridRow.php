<?php

namespace App\Domain\Training\Queries;

use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\SessionKind;

/**
 * One client's week: seven days keyed by date, each holding what happened — a held session, a
 * cancellation or a no-show, or nothing at all.
 */
readonly class WeekGridRow
{
    /**
     * @param  array<string, SessionKind|null>  $days
     */
    public function __construct(
        public Client $client,
        public array $days,
    ) {}

    /**
     * Nothing logged all week — the row that gets the "Wbij sesję" button.
     */
    public function isUntouched(): bool
    {
        return collect($this->days)->every(fn (?SessionKind $kind) => $kind === null);
    }
}
