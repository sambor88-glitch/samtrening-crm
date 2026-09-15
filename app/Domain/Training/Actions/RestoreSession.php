<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;

/**
 * The other half of "Cofnij" — and its own line in the log, because an undo is a change too.
 */
class RestoreSession
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    public function handle(User $actor, TrainingSession $session): TrainingSession
    {
        $session->restore();

        // Back in line for the prepayment, ahead of any session logged after it.
        $this->pool->allocate($session->client);

        $this->log->record(
            $actor,
            'Cofnął usunięcie sesji',
            $session->client->name.' · '.$session->date->format('d.m.Y').' · '.Money::format($session->price),
        );

        return $session;
    }
}
