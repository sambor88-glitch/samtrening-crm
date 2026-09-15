<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;

/**
 * Every amount can be overridden after the fact — that is the point of logging by hand. Both
 * values go to the log, so a later argument about who changed what has an answer. Two people
 * editing the same session both leave a line; the later write wins (docs/START-TUTAJ.md §11).
 */
class UpdateSessionPrice
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    public function handle(User $actor, TrainingSession $session, int $price): TrainingSession
    {
        $before = $session->price;

        if ($before === $price) {
            return $session;
        }

        // A prepayment's share never exceeds the price, not even until the pool has been worked
        // out again — the balance query would read the difference as a negative debt.
        $session->forceFill([
            'price' => $price,
            'prepaid_amount' => min((int) $session->prepaid_amount, $price),
        ])->save();

        // A cheaper session leaves money in the pool for the next one; a dearer one may not fit.
        $this->pool->allocate($session->client);
        $session->refresh();

        $this->log->record(
            $actor,
            'Zmienił kwotę sesji',
            $session->client->name.' · '.$session->date->format('d.m.Y').' · '
                .Money::format($before).' → '.Money::format($price),
        );

        return $session;
    }
}
