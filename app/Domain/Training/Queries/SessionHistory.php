<?php

namespace App\Domain\Training\Queries;

use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything one trainer has logged, newest first — docs/SPEC-EKRANY.md ekran 7. Deleted
 * sessions fall away with the soft delete; archived clients keep their history here, because
 * the work was done and paid for like any other.
 */
class SessionHistory
{
    public function forTrainer(User $trainer, int $limit = 25): SessionPage
    {
        $query = TrainingSession::query()
            ->whereHas('client', fn (Builder $client) => $client->forTrainer($trainer));

        return new SessionPage(
            rows: (clone $query)
                ->with('client:id,name')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            total: $query->count(),
        );
    }

    /**
     * Everything inside a month or a year — the earnings screen shows the range whole, and a
     * range is bounded by definition.
     *
     * @return Collection<int, TrainingSession>
     */
    public function inRange(User $trainer, DateRange $range): Collection
    {
        return TrainingSession::query()
            ->whereHas('client', fn (Builder $client) => $client->forTrainer($trainer))
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->with('client:id,name')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }
}
