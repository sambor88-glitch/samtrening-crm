<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;

/**
 * Takes a session off the card without taking it out of the database — the row stays, soft
 * deleted, so "Cofnij" has something to bring back and the books keep their trace.
 */
class DeleteSession
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $actor, TrainingSession $session): TrainingSession
    {
        $wasOwed = $session->isPayable();

        $session->delete();

        $this->log->record(
            $actor,
            'Usunął sesję',
            $session->client->name.' · '.$session->date->format('d.m.Y').' · '
                .Money::format($session->price).($wasOwed ? ' · zdjęte z salda' : ''),
        );

        return $session;
    }
}
