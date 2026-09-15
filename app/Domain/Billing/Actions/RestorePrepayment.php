<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Team\Models\User;
use App\Support\Money;

/**
 * The other half of "Cofnij" for a prepayment — its own line in the log, because an undo is a
 * change too. A second click on the toast finds nothing to restore and writes nothing.
 */
class RestorePrepayment
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    public function handle(User $actor, Prepayment $prepayment): Prepayment
    {
        if (! $prepayment->trashed()) {
            return $prepayment;
        }

        $prepayment->restore();

        $this->pool->allocate($prepayment->client);

        $this->log->record(
            $actor,
            'Cofnął usunięcie wpłaty z góry',
            $prepayment->client->name.' · '.$prepayment->paid_on->format('d.m.Y').' · '.Money::format($prepayment->amount),
        );

        return $prepayment;
    }
}
