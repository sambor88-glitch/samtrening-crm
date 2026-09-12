<?php

namespace App\Domain\Billing;

use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;

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

        return $this->summarise(
            $this->sessions($range)->whereHas('client', fn (Builder $query) => $query->where('trainer_id', $trainerId))
        );
    }

    /**
     * The same arithmetic for the whole studio — the owner's dashboard.
     */
    public function forStudio(DateRange $range): EarningsSummary
    {
        return $this->summarise($this->sessions($range));
    }

    /**
     * Compared as plain dates, never against a datetime — see Support\DateRange.
     *
     * @return Builder<TrainingSession>
     */
    private function sessions(DateRange $range): Builder
    {
        return TrainingSession::query()->whereBetween('date', [$range->firstDay(), $range->lastDay()]);
    }

    /**
     * @param  Builder<TrainingSession>  $query
     */
    private function summarise(Builder $query): EarningsSummary
    {
        $sessions = $query->get(['price', 'kind', 'payment_status']);

        $missed = $sessions->reject(fn (TrainingSession $session) => $session->isCompleted());

        return new EarningsSummary(
            revenue: (int) $sessions->sum('price'),
            completedSessions: $sessions->count() - $missed->count(),
            paid: (int) $sessions->where('payment_status', PaymentStatus::Paid)->sum('price'),
            owed: (int) $sessions->filter(fn (TrainingSession $session) => $session->isPayable())->sum('price'),
            missedSessions: $missed->count(),
            missedRevenue: (int) $missed->sum('price'),
        );
    }
}
