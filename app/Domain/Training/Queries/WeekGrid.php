<?php

namespace App\Domain\Training\Queries;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\DormantClients;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The week closer — docs/START-TUTAJ.md §6. Monday to Sunday of the current ISO week against the
 * trainer's active clients, so the gaps are visible: an empty row is a week nobody logged.
 *
 * Clients who have gone quiet are left out (`DormantClients`), because they get their own tile
 * and would otherwise sit here as seven empty boxes every week.
 */
class WeekGrid
{
    public function __construct(private readonly DormantClients $dormant) {}

    public function forTrainer(User $trainer): WeekGridData
    {
        // The week comes from PHP in Europe/Warsaw, never from the database (§6).
        $monday = CarbonImmutable::now(config('app.timezone'))->startOfWeek();
        $days = collect(range(0, 6))->map(fn (int $offset) => $monday->addDays($offset));

        $clients = Client::query()
            ->forTrainer($trainer)
            ->where('archived', false)
            ->whereKeyNot($this->dormant->idsForTrainer($trainer)->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        $sessions = TrainingSession::query()
            ->whereIn('client_id', $clients->modelKeys())
            ->whereBetween('date', [$monday->toDateString(), $monday->addDays(6)->toDateString()])
            ->get(['client_id', 'date', 'kind']);

        $rows = $clients->map(fn (Client $client) => new WeekGridRow(
            client: $client,
            days: $days
                ->mapWithKeys(fn (CarbonImmutable $day) => [
                    $day->toDateString() => $this->stateOn($sessions, $client, $day),
                ])
                ->all(),
        ));

        return new WeekGridData($monday, $days->all(), $rows);
    }

    /**
     * Two sessions on one day is not a conflict: one held session makes the day held, whatever
     * else happened around it.
     *
     * @param  Collection<int, TrainingSession>  $sessions
     */
    private function stateOn($sessions, Client $client, CarbonImmutable $day): ?SessionKind
    {
        $onThatDay = $sessions->filter(
            fn (TrainingSession $session) => $session->client_id === $client->getKey()
                && $session->date->toDateString() === $day->toDateString()
        );

        if ($onThatDay->isEmpty()) {
            return null;
        }

        return $onThatDay->contains(fn (TrainingSession $session) => $session->kind === SessionKind::Completed)
            ? SessionKind::Completed
            : $onThatDay->first()->kind;
    }
}
