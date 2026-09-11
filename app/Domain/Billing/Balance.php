<?php

namespace App\Domain\Billing;

use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;

/**
 * The only place in the app that computes a balance — docs/START-TUTAJ.md §4 rule 2 and §6.
 * Nothing is stored: the debt is always the sum of sessions that are neither paid nor waived,
 * in grosze. Deleted sessions drop out on their own (soft deletes), and archived clients keep
 * their balance — the archive hides a card, it does not forgive a debt.
 */
class Balance
{
    public function forClient(Client $client): int
    {
        return (int) $client->sessions()
            ->whereNotIn('payment_status', PaymentStatus::SETTLED)
            ->sum('price');
    }

    /**
     * Balances for a whole list in one query, keyed by client id. Clients who owe nothing are
     * missing from the result — read it with `$balances[$id] ?? 0`.
     *
     * @param  array<int, int>  $clientIds
     * @return array<int, int>
     */
    public function forClients(array $clientIds): array
    {
        return TrainingSession::query()
            ->whereIn('client_id', $clientIds)
            ->whereNotIn('payment_status', PaymentStatus::SETTLED)
            ->groupBy('client_id')
            ->selectRaw('client_id, sum(price) as owed')
            ->pluck('owed', 'client_id')
            ->map(fn ($owed) => (int) $owed)
            ->all();
    }

    /**
     * The date of the oldest session still owed — where "how long has this been dragging on"
     * is counted from.
     */
    public function owedSince(Client $client): ?CarbonImmutable
    {
        $date = $client->sessions()
            ->whereNotIn('payment_status', PaymentStatus::SETTLED)
            ->min('date');

        return $date ? CarbonImmutable::parse($date, config('app.timezone')) : null;
    }

    /**
     * Days since that oldest owed session. Counted in PHP, in the studio's time zone — the
     * database never decides what "today" means (docs/START-TUTAJ.md §2).
     */
    public function daysOwed(Client $client): ?int
    {
        $since = $this->owedSince($client);

        if (! $since) {
            return null;
        }

        return (int) $since->startOfDay()->diffInDays(CarbonImmutable::now(config('app.timezone'))->startOfDay());
    }

    /**
     * Past the studio's patience: a debt older than `settings.reminder_threshold_days`.
     * Pass the threshold in when you already have the settings row in hand.
     */
    public function isOverdue(Client $client, ?int $thresholdDays = null): bool
    {
        if ($this->forClient($client) <= 0) {
            return false;
        }

        $days = $this->daysOwed($client);

        return $days !== null
            && $days > ($thresholdDays ?? Setting::current()->reminder_threshold_days);
    }
}
