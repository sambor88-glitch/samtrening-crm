<?php

namespace App\Domain\Calendar;

use Carbon\CarbonImmutable;

/**
 * One entry in the studio's diary, reduced to what logging a session needs:
 * when it was, what it was called, and the id that tells one occurrence of a
 * recurring training from the next.
 *
 * Nothing here decides whose session it was — `Agent\CalendarAliases` does that
 * from the title, and it refuses to guess (SC-64).
 */
class CalendarEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly CarbonImmutable $startsAt,
        public readonly bool $allDay,
    ) {}

    /**
     * Builds one from Google's payload, or null when the entry is not a training:
     * a cancelled slot, a whole-day note, or anything without a title to match on.
     *
     * A whole-day entry is dropped on purpose. The studio books trainings by the
     * hour; "Urlop" spanning a week is not a session and must never be offered as
     * one.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromGoogle(array $item, string $timezone): ?self
    {
        $title = trim((string) ($item['summary'] ?? ''));

        if ($title === '' || ($item['status'] ?? null) === 'cancelled') {
            return null;
        }

        $start = $item['start'] ?? [];
        $moment = $start['dateTime'] ?? $start['date'] ?? null;

        if (! is_string($moment) || $moment === '') {
            return null;
        }

        $allDay = ! isset($start['dateTime']);

        if ($allDay) {
            return null;
        }

        return new self(
            id: (string) ($item['id'] ?? $moment.'|'.$title),
            title: $title,
            // Google answers in the calendar's own offset; the studio counts days in
            // Europe/Warsaw, and a session booked at 23:30 must not slide into the
            // next day (docs/START-TUTAJ.md §2).
            startsAt: CarbonImmutable::parse($moment)->setTimezone($timezone),
            allDay: false,
        );
    }

    /**
     * The day the session belongs to, as `training_sessions.date` stores it.
     */
    public function date(): string
    {
        return $this->startsAt->toDateString();
    }
}
