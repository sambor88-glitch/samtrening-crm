<?php

namespace App\Domain\Clients\Queries;

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Who has gone quiet — docs/SPEC-EKRANY.md „Zmiany — 10.09.2026". Twenty-one days without a
 * session is long enough that nobody remembers to reach out on their own.
 *
 * A client with no session at all is not dormant: they were just added, and they belong in the
 * week grid where their first session can be logged.
 */
class DormantClients
{
    public const int SILENT_DAYS = 21;

    /**
     * @return Collection<int, DormantClient>
     */
    public function forTrainer(User $trainer): Collection
    {
        // "Today" is computed in PHP and passed in: the connection runs in the server's zone,
        // so NOW() would answer a different question (docs/START-TUTAJ.md §6).
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        // `withMax` is a subquery select, not an aggregate, so it cannot be filtered with HAVING
        // (SQLite refuses outright). One roster is tens of rows — the cut happens in PHP.
        return Client::query()
            ->forTrainer($trainer)
            ->where('archived', false)
            ->withMax('sessions', 'date')
            ->get()
            ->filter(fn (Client $client) => filled($client->sessions_max_date))
            ->map(fn (Client $client) => new DormantClient(
                client: $client,
                lastOn: $last = CarbonImmutable::parse((string) $client->sessions_max_date, config('app.timezone'))->startOfDay(),
                days: (int) $last->diffInDays($today),
            ))
            ->filter(fn (DormantClient $row) => $row->days >= self::SILENT_DAYS)
            ->sortByDesc(fn (DormantClient $row) => $row->days)
            ->values();
    }

    /**
     * Just the ids — the week grid only needs to know who to leave out.
     *
     * @return Collection<int, int>
     */
    public function idsForTrainer(User $trainer): Collection
    {
        return $this->forTrainer($trainer)->map(fn (DormantClient $row) => $row->client->getKey());
    }
}
