<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Team\Models\User;
use App\Support\Money;

/**
 * Takes a mistaken entry off the card without taking it out of the database — soft deleted, so
 * "Cofnij" has something to bring back. The sessions it paid for go back on the balance.
 */
class DeletePrepayment
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    public function handle(User $actor, Prepayment $prepayment): Prepayment
    {
        if ($prepayment->trashed()) {
            return $prepayment;
        }

        $prepayment->delete();

        $this->pool->allocate($prepayment->client);

        $this->log->record(
            $actor,
            'Usunął wpłatę z góry',
            $prepayment->client->name.' · '.$prepayment->paid_on->format('d.m.Y').' · '.Money::format($prepayment->amount),
        );

        return $prepayment;
    }
}
