<?php

namespace App\Domain\Training\Queries;

use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Collection;

/**
 * A slice of the history and how much of it there is — enough for "pokaż starsze" without
 * counting rows in the view.
 */
readonly class SessionPage
{
    /**
     * @param  Collection<int, TrainingSession>  $rows
     */
    public function __construct(
        public Collection $rows,
        public int $total,
    ) {}

    public function hasMore(): bool
    {
        return $this->rows->count() < $this->total;
    }
}
