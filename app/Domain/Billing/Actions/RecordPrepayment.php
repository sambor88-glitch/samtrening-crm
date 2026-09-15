<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Balance;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Support\Money;

/**
 * The client paid up front — cash, transfer or BLIK, marked by a person like every other payment.
 * The money goes into the client's pool and straight to whatever they still owe, oldest first;
 * the rest waits for the sessions to come. The log says how much of the balance it paid off,
 * because "I paid a thousand, why is it six hundred" deserves an answer.
 */
class RecordPrepayment
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly Balance $balance,
        private readonly PrepaymentPool $pool,
    ) {}

    /**
     * @param  int  $amount  grosze
     * @param  string  $paidOn  the day the money came in
     */
    public function handle(User $actor, Client $client, int $amount, string $paidOn): Prepayment
    {
        $owedBefore = $this->balance->forClient($client);

        $prepayment = $client->prepayments()->create(['amount' => $amount, 'paid_on' => $paidOn]);

        $this->pool->allocate($client);

        $paidOff = $owedBefore - $this->balance->forClient($client);

        $this->log->record(
            $actor,
            'Zapisał wpłatę z góry',
            $client->name.' · '.Money::format($amount).' · '.$prepayment->paid_on->format('d.m.Y')
                .($paidOff > 0 ? ' · pokryła '.Money::format($paidOff).' salda' : ''),
        );

        return $prepayment;
    }
}
