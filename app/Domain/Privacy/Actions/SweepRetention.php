<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Support\Plural;
use Carbon\CarbonImmutable;

/**
 * The monthly sweep — docs/START-TUTAJ.md §10. Archived cards older than the studio's retention
 * period are anonymised, never deleted: the sessions behind them are what the tax office was told.
 *
 * The clock runs from the last session, not from when the card was archived. Somebody archived in
 * January whose last training was four years ago has been dormant for four years, not for eight
 * months, and the law counts the data, not the filing.
 */
class SweepRetention
{
    public function __construct(
        private readonly AnonymizeClient $anonymize,
        private readonly ActivityLogger $log,
    ) {}

    public function handle(): int
    {
        $months = (int) (Setting::query()->value('retention_months') ?? 60);
        $cutoff = CarbonImmutable::now(config('app.timezone'))->startOfDay()->subMonths($months);

        $swept = 0;

        Client::query()
            ->where('archived', true)
            ->where('name', 'not like', 'Dane usunięte #%')
            ->withMax('sessions', 'date')
            ->get()
            ->filter(function (Client $client) use ($cutoff) {
                $last = $client->sessions_max_date
                    ? CarbonImmutable::parse((string) $client->sessions_max_date, config('app.timezone'))
                    : CarbonImmutable::parse($client->created_at, config('app.timezone'));

                return $last->startOfDay()->lessThanOrEqualTo($cutoff);
            })
            ->each(function (Client $client) use (&$swept) {
                // No actor: nobody pressed anything, the retention period simply ran out.
                $this->anonymize->handle(null, $client);
                $swept++;
            });

        if ($swept > 0) {
            $this->log->record(
                null,
                'Wyczyścił kartoteki po retencji',
                Plural::of($swept, 'kartoteka', 'kartoteki', 'kartotek').' · retencja '.$months.' mies.',
            );
        }

        return $swept;
    }
}
