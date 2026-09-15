<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;

/**
 * Takes a session off the card without taking it out of the database — the row stays, soft
 * deleted, so "Cofnij" has something to bring back and the books keep their trace.
 */
class DeleteSession
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    public function handle(User $actor, TrainingSession $session): TrainingSession
    {
        $trace = match (true) {
            $session->isPayable() => ' · zdjęte z salda',
            $session->isPrepaid() => ' · wraca do przedpłaty',
            default => '',
        };

        $session->delete();

        // What it took from a prepayment goes back to the pool, and the next session in line
        // may be paid for now.
        $this->pool->allocate($session->client);

        $this->log->record(
            $actor,
            'Usunął sesję',
            $session->client->name.' · '.$session->date->format('d.m.Y').' · '
                .Money::format($session->price).$trace,
        );

        return $session;
    }
}
