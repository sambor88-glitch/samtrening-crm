<?php

namespace App\Domain\Team\Queries;

use App\Domain\Billing\Earnings;
use App\Domain\Team\Models\User;
use App\Support\DateRange;
use Illuminate\Support\Collection;

/**
 * The whole team with their numbers for a range — docs/SPEC-EKRANY.md ekran 13. A studio has
 * three people, so a query per trainer is cheaper to read than a join nobody trusts.
 */
class TeamRoster
{
    public function __construct(private readonly Earnings $earnings) {}

    /**
     * @return Collection<int, TeamRosterRow>
     */
    public function forStudio(DateRange $range): Collection
    {
        return User::query()
            ->withCount(['clients as active_clients_count' => fn ($query) => $query->where('archived', false)])
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get()
            ->map(function (User $trainer) use ($range) {
                $earned = $this->earnings->forTrainer($trainer, $range);

                return new TeamRosterRow(
                    trainer: $trainer,
                    clients: (int) $trainer->active_clients_count,
                    sessions: $earned->completedSessions,
                    revenue: $earned->revenue,
                );
            });
    }
}
