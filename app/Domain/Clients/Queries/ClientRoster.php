<?php

namespace App\Domain\Clients\Queries;

use App\Domain\Billing\Balance;
use App\Domain\Clients\Enums\RosterFilter;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The client list — docs/SPEC-EKRANY.md ekran 5 for a trainer, ekran 14 for the studio. Whatever
 * the filter, the work costs the same handful of queries: one for the count, one for the page,
 * one for the tags, one for every balance at once. Three hundred clients must not mean three
 * hundred round trips.
 */
class ClientRoster
{
    public function __construct(private readonly Balance $balance) {}

    public function forTrainer(
        User $trainer,
        RosterFilter $filter = RosterFilter::Active,
        string $search = '',
    ): Roster {
        $base = Client::query()
            ->forTrainer($trainer)
            ->where('archived', $filter === RosterFilter::Archived);

        return $this->build($base, $search, $filter);
    }

    /**
     * The owner's version: everyone in the studio, archived included — the archive is a tag here,
     * not a separate screen — optionally narrowed to one trainer.
     */
    public function forStudio(?int $trainerId = null, string $search = ''): Roster
    {
        $base = Client::query()
            ->when($trainerId, fn (Builder $query, int $id) => $query->where('trainer_id', $id))
            ->with('trainer:id,name');

        return $this->build($base, $search, null);
    }

    /**
     * @param  Builder<Client>  $base
     */
    private function build(Builder $base, string $search, ?RosterFilter $filter): Roster
    {
        // The "Y" of "X z Y": everything the filter could have shown, before searching.
        $total = (clone $base)->count();

        $clients = $base
            ->when($this->needle($search), fn (Builder $query, string $needle) => $query->where(
                fn (Builder $match) => $match
                    ->whereLike('name', "%{$needle}%")
                    ->orWhereLike('phone', "%{$needle}%")
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->whereLike('label', "%{$needle}%"))
            ))
            ->when($filter === RosterFilter::Online, fn (Builder $query) => $query->whereHas(
                'tags', fn (Builder $tags) => $tags->whereLike('label', '%E-trening%')
            ))
            ->with('tags')
            ->withMax('sessions', 'date')
            ->orderBy('name')
            ->get();

        $balances = $this->balance->forClients($clients->modelKeys());

        /** @var Collection<int, RosterRow> $rows */
        $rows = $clients->map(fn (Client $client) => new RosterRow(
            client: $client,
            balance: $balances[$client->getKey()] ?? 0,
            lastSessionOn: $client->sessions_max_date
                ? CarbonImmutable::parse($client->sessions_max_date, config('app.timezone'))
                : null,
        ));

        if ($filter === RosterFilter::Owing) {
            $rows = $rows->filter(fn (RosterRow $row) => $row->owes())->values();
        }

        return new Roster($rows, $total);
    }

    /**
     * Wildcards are stripped rather than escaped: MySQL and SQLite disagree about escaping in
     * LIKE, and no name, phone or tag in this studio contains % or _.
     */
    private function needle(string $search): ?string
    {
        $needle = str_replace(['%', '_'], '', trim($search));

        return $needle === '' ? null : $needle;
    }
}
