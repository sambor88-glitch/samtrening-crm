<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Team\Notifications\TrainerReminder;
use App\Support\Money;

/**
 * The owner reminds the trainer, never the client — settling is the trainer's relationship and
 * the studio only keeps an eye on it (docs/SPEC-EKRANY.md ekran 15).
 */
class NudgeTrainer
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly Balance $balance,
    ) {}

    public function handle(User $owner, Client $client): void
    {
        $owed = $this->balance->forClient($client);

        $client->trainer->notify(new TrainerReminder($client, $owed, $owner->name));

        $this->log->record(
            $owner,
            'Przypomniał trenerowi',
            $client->trainer->name.' · '.$client->name.' · '.Money::format($owed),
        );
    }
}
