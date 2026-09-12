<?php

namespace App\Domain\Billing\Queries;

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who owes what — docs/SPEC-EKRANY.md ekran 8. Grouped in the database rather than client by
 * client, so a studio with a few hundred cards still costs two queries. Arrears are cumulative
 * and never filtered by month: a debt does not belong to a month (docs/START-TUTAJ.md §6).
 */
class Outstanding
{
    /**
     * @return Collection<int, OutstandingRow>
     */
    public function forTrainer(User $trainer): Collection
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        $totals = $this->owedSessions($trainer)
            ->selectRaw('client_id, count(*) as sessions, sum(price) as amount, min(date) as oldest')
            ->selectRaw('max(case when payment_status = ? then 1 else 0 end) as requested', [PaymentStatus::Requested->value])
            ->groupBy('client_id')
            ->get();

        $clients = Client::query()->whereKey($totals->pluck('client_id'))->get()->keyBy('id');

        return $totals
            ->map(function ($row) use ($clients, $today) {
                $oldest = CarbonImmutable::parse($row->oldest, config('app.timezone'))->startOfDay();

                return new OutstandingRow(
                    client: $clients[$row->client_id],
                    sessions: (int) $row->sessions,
                    amount: (int) $row->amount,
                    oldestOn: $oldest,
                    days: (int) $oldest->diffInDays($today),
                    requested: (bool) $row->requested,
                );
            })
            ->sortByDesc(fn (OutstandingRow $row) => $row->amount)
            ->values();
    }

    /**
     * Everything the trainer is still waiting for, across all balances.
     */
    public function totalForTrainer(User $trainer): int
    {
        return (int) $this->owedSessions($trainer)->sum('price');
    }

    /**
     * What actually came in during the range — cash, transfer or BLIK, all marked by hand.
     */
    public function paidIn(User $trainer, DateRange $range): int
    {
        return (int) $this->sessionsOf($trainer)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->sum('price');
    }

    /**
     * Requests that went out and brought nothing back yet.
     */
    public function pendingRequests(User $trainer): int
    {
        return $this->sessionsOf($trainer)->where('payment_status', PaymentStatus::Requested)->count();
    }

    /**
     * @return Builder<TrainingSession>
     */
    private function sessionsOf(User $trainer): Builder
    {
        return TrainingSession::query()
            ->whereHas('client', fn (Builder $client) => $client->forTrainer($trainer));
    }

    /**
     * @return Builder<TrainingSession>
     */
    private function owedSessions(User $trainer): Builder
    {
        return $this->sessionsOf($trainer)->whereNotIn('payment_status', PaymentStatus::SETTLED);
    }
}
