<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Messaging\SmsCount;
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
     * @throws MessageNotPossible
     */
    public function handle(
        User $sender,
        Client $client,
        string $text,
        string $subject,
        ?User $actor = null,
    ): SmsCount {
        // Even texts that do not mention BLIK are blocked: a trainer without one cannot be paid,
        // and the studio would rather fix that first (docs/START-TUTAJ.md §9).
        if (blank($sender->blik_number)) {
            throw MessageNotPossible::withoutBlikNumber();
        }

        if (blank($client->phone)) {
            throw MessageNotPossible::withoutPhone($client);
        }

        // The nightly run has nobody logged in, so a failure there is signed "System".
        SendSmsMessage::dispatch(
            clientId: $client->getKey(),
            phone: $client->phone,
            text: $text,
            actorId: $actor?->getKey(),
            subject: $subject,
        );

        return $this->counter->count($text);
    }
}
