<?php

namespace App\Domain\Agent\Queries;

use App\Domain\Agent\CalendarAliases;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The roster the dashboard matches calendar events against — docs/AGENT-API.md §4.
 *
 * The whole studio, archived clients included: a former client still counts in the months they
 * trained, and the dashboard looks back over those months.
 *
 * Grouped in the database rather than card by card, so the endpoint costs four queries whatever
 * the roster looks like.
 */
class ClientList
{
    public function __construct(
        private readonly Balance $balance,
        private readonly CalendarAliases $aliases,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        /** @var Collection<int, Client> $clients */
        $clients = Client::query()->orderBy('name')->orderBy('id')->get();

        if ($clients->isEmpty()) {
            return [];
        }

        $ids = $clients->modelKeys();

        $owed = $this->balance->forClients($ids);
        $aliases = $this->aliases->forRoster($clients);
        $lastSessions = $this->lastSessions($ids);
        $prepaid = $this->unusedPrepayments($ids);

        return $clients->map(fn (Client $client) => [
            'id' => $client->getKey(),
            'name' => $client->name,
            'active' => ! $client->archived,
            'rate_minor' => (int) $client->rate,
            'currency' => 'PLN',
            'calendar_aliases' => $aliases[$client->getKey()] ?? [],
            // The spec signs this the other way round from the CRM: negative is a debt, positive
            // is money the client has paid in and not yet trained off (docs/AGENT-API.md §4).
            'balance_minor' => ($prepaid[$client->getKey()] ?? 0) - ($owed[$client->getKey()] ?? 0),
            'last_session' => $lastSessions[$client->getKey()] ?? null,
            // The CRM does not hold the diary — the schedule lives in Google Calendar and is
            // never copied here (README, docs/START-TUTAJ.md §3). Always null, on purpose: the
            // dashboard already reads the calendar and knows this better than the CRM could.
            'next_session' => null,
        ])->all();
    }

    /**
     * The last day each client trained. Cancellations and no-shows do not count — nobody trained.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function lastSessions(array $ids): array
    {
        return TrainingSession::query()
            ->whereIn('client_id', $ids)
            ->where('kind', SessionKind::Completed)
            ->groupBy('client_id')
            ->selectRaw('client_id, max(date) as last_date')
            ->pluck('last_date', 'client_id')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->all();
    }

    /**
     * Money paid up front that no session has eaten yet. `Billing\PrepaymentPool` decides which
     * sessions a prepayment covers and writes it onto them, so both halves of this subtraction
     * come from the same rows — never from a stored balance.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function unusedPrepayments(array $ids): array
    {
        $paidIn = DB::table('prepayments')
            ->whereIn('client_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('client_id')
            ->selectRaw('client_id, sum(amount) as total')
            ->pluck('total', 'client_id');

        if ($paidIn->isEmpty()) {
            return [];
        }

        $used = TrainingSession::query()
            ->whereIn('client_id', $paidIn->keys()->all())
            ->groupBy('client_id')
            ->selectRaw('client_id, sum(prepaid_amount) as total')
            ->pluck('total', 'client_id');

        return $paidIn
            ->map(fn ($total, $clientId) => max(0, (int) $total - (int) ($used[$clientId] ?? 0)))
            ->all();
    }
}
