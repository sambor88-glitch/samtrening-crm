<?php

namespace App\Domain\Billing;

use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;

/**
 * What a trainer earned in a range — docs/START-TUTAJ.md §6. Everything logged in the range
 * counts as money, paid or not; only held sessions count as sessions. Archived clients stay in
 * the numbers: their past sessions were worked and paid for like any other.
 */
class Earnings
{
    public function forTrainer(User|int $trainer, DateRange $range): EarningsSummary
    {
        $trainerId = $trainer instanceof User ? $trainer->getKey() : $trainer;

        // Compared as plain dates, never against a datetime — see Support\DateRange.
        $sessions = TrainingSession::query()
            ->whereHas('client', fn ($query) => $query->where('trainer_id', $trainerId))
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->get(['price', 'kind']);

        return new EarningsSummary(
            revenue: (int) $sessions->sum('price'),
            completedSessions: $sessions->where('kind', SessionKind::Completed)->count(),
        );
    }
}
