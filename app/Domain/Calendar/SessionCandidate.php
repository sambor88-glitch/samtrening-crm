<?php

namespace App\Domain\Calendar;

use App\Domain\Clients\Models\Client;
use Carbon\CarbonImmutable;

/**
 * A training that sits in the diary and not yet in the CRM — one row of the list
 * the trainer confirms.
 *
 * Nothing here is a session. It becomes one when somebody says so, which is the
 * whole point of the screen: the calendar does not know about a cancellation half
 * an hour before, and money must not appear because a diary entry did.
 */
class SessionCandidate
{
    public function __construct(
        public readonly CalendarEvent $event,
        public readonly ?Client $client,
        /** Clients the title could equally mean — filled only when the match was ambiguous. */
        public readonly array $ambiguous = [],
    ) {}

    /**
     * Ready to be logged: exactly one client, and no doubt about which.
     */
    public function isMatched(): bool
    {
        return $this->client !== null;
    }

    /**
     * The title matched more than one card. Deliberately not resolved here —
     * charging the wrong client is worse than asking (SC-64).
     */
    public function isAmbiguous(): bool
    {
        return $this->client === null && $this->ambiguous !== [];
    }

    /**
     * What to charge, in grosze. The client's rate is the starting point, never the
     * last word: `training_sessions.price` may differ, so the screen keeps it editable.
     */
    public function suggestedPrice(): int
    {
        return (int) ($this->client?->rate ?? 0);
    }

    public function date(): string
    {
        return $this->event->date();
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->event->startsAt;
    }

    /**
     * A stable handle for the checkbox on the screen, so ticking survives a refresh.
     */
    public function key(): string
    {
        return $this->event->id;
    }
}
