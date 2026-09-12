<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Messaging\StatementRun;
use App\Domain\Team\Models\User;
use App\Support\DateRange;
use App\Support\Plural;
use App\Support\PolishMonth;

/**
 * One statement per client who owes something. Nothing here runs on a schedule: the amount comes
 * from sessions typed in by hand, and a robot would send last month's total the moment somebody
 * forgot to log two sessions (docs/START-TUTAJ.md §3).
 */
class SendMonthlyStatements
{
    public function __construct(
        private readonly Outstanding $outstanding,
        private readonly SendMonthlyStatement $statement,
        private readonly ActivityLogger $log,
    ) {}

    public function handle(User $trainer, DateRange $month): StatementRun
    {
        $sent = 0;
        $skipped = [];

        foreach ($this->outstanding->forTrainer($trainer) as $row) {
            /** @var OutstandingRow $row */
            try {
                $this->statement->handle($trainer, $row->client, $month);
                $sent++;
            } catch (MessageNotPossible $blocked) {
                // A client without an e-mail is not a failed run — the rest still goes out.
                $skipped[] = $row->client->name;
            }
        }

        $this->log->record(
            $trainer,
            'Wysłał zbiorcze podsumowania',
            Plural::of($sent, 'klient', 'klienci', 'klientów').' · '.PolishMonth::withYear($month->start()),
        );

        return new StatementRun($sent, $skipped);
    }
}
