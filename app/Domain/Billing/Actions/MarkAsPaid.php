<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Support\Money;

/**
 * Money arrives in cash, by transfer or through BLIK, and none of that reaches this app — so a
 * person marks it, never a robot. Everything the client still owed becomes paid at once, which
 * is how it works at the door: they hand over what is due, not a session at a time.
 */
class MarkAsPaid
{
    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @return int the amount marked as paid, in grosze
     */
    public function handle(User $actor, Client $client): int
    {
        $owed = $client->sessions()->whereNotIn('payment_status', PaymentStatus::SETTLED)->get();

        if ($owed->isEmpty()) {
            return 0;
        }

        $amount = (int) $owed->sum('price');

        $client->sessions()
            ->whereKey($owed->modelKeys())
            ->update(['payment_status' => PaymentStatus::Paid]);

        $this->log->record($actor, 'Odznaczył płatność', $client->name.' · '.Money::format($amount));

        return $amount;
    }
}
