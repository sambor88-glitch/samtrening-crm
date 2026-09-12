<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Messaging\SmsCount;
use App\Domain\Messaging\SmsNotPossible;
use App\Domain\Messaging\SmsSegmentCounter;
use App\Domain\Team\Models\User;

/**
 * Puts one SMS on the queue after checking the two things that make a message pointless: no BLIK
 * number on the trainer, no phone number on the client. Nothing is sent inline — the carrier
 * being slow must never hold up a session being saved.
 */
class SendSms
{
    public function __construct(private readonly SmsSegmentCounter $counter) {}

    /**
     * @throws SmsNotPossible
     */
    public function handle(User $trainer, Client $client, string $text, string $subject): SmsCount
    {
        // Even texts that do not mention BLIK are blocked: a trainer without one cannot be paid,
        // and the studio would rather fix that first (docs/START-TUTAJ.md §9).
        if (blank($trainer->blik_number)) {
            throw SmsNotPossible::withoutBlikNumber();
        }

        if (blank($client->phone)) {
            throw SmsNotPossible::withoutPhone($client);
        }

        SendSmsMessage::dispatch(
            clientId: $client->getKey(),
            phone: $client->phone,
            text: $text,
            actorId: $trainer->getKey(),
            subject: $subject,
        );

        return $this->counter->count($text);
    }
}
