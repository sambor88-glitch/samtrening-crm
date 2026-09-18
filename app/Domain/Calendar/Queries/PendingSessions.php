<?php

namespace App\Domain\Calendar\Queries;

use App\Domain\Agent\CalendarAliases;
use App\Domain\Calendar\CalendarEvent;
use App\Domain\Calendar\GoogleCalendar;
use App\Domain\Calendar\SessionCandidate;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Trainings that happened according to the diary and are not in the CRM yet — SC-65.
 *
 * Matching runs against the whole studio, never one roster: whether "anna" points at
 * one client or two is a question about everybody on the books, and asking it per
 * trainer would let an alias look unique that is not. What the trainer then sees is
 * narrowed to their own cards, the way every other screen is (docs/START-TUTAJ.md §7).
 */
class PendingSessions
{
    public function __construct(
        private readonly GoogleCalendar $calendar,
        private readonly CalendarAliases $aliases,
    ) {}

    /**
     * @return Collection<int, SessionCandidate>
     */
    public function forTrainer(User $trainer): Collection
    {
        if (! $this->calendar->isConfigured()) {
            return collect();
        }

        [$from, $to] = $this->range($trainer);

        $roster = Client::query()->get();
        $aliases = $this->aliases->forRoster($roster);
        $mine = $roster->where('trainer_id', $trainer->getKey())->keyBy(fn (Client $c) => $c->getKey());

        $logged = $this->alreadyLogged($mine->keys()->all(), $from, $to);

        return $this->calendar->between($from, $to)
            ->map(fn (CalendarEvent $event) => $this->match($event, $roster, $aliases))
            ->filter(function (?SessionCandidate $candidate) use ($mine, $logged) {
                if ($candidate === null) {
                    return false;
                }

                // Somebody else's client is somebody else's screen.
                if ($candidate->isMatched() && ! $mine->has($candidate->client->getKey())) {
                    return false;
                }

                // Already in the CRM — typed by hand, or confirmed here earlier.
                return ! ($candidate->isMatched()
                    && $logged->contains($candidate->client->getKey().'|'.$candidate->date()));
            })
            ->values();
    }

    /**
     * Which days to look at: back to the last session this trainer logged, and no
     * further than the studio's patience for scrolling (config/calendar.php).
     * A trainer who has logged nothing yet gets the full window.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(User $trainer): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $earliest = $today->subDays(max(1, (int) config('calendar.lookback_days', 30)));

        $last = TrainingSession::query()
            ->whereHas('client', fn ($query) => $query->where('trainer_id', $trainer->getKey()))
            ->max('date');

        $from = $last === null
            ? $earliest
            : CarbonImmutable::parse($last, config('app.timezone'))->startOfDay();

        return [$from->lessThan($earliest) ? $earliest : $from, $today];
    }

    /**
     * Client and day of everything already logged in the window, as "id|Y-m-d".
     *
     * A day, not a moment: the diary says 10:00 and the CRM stores only the date, so
     * two trainings on one day with one client look alike. That is the safer way
     * round — proposing a session somebody already typed in would double the money.
     *
     * @param  array<int, int>  $clientIds
     * @return Collection<int, string>
     */
    private function alreadyLogged(array $clientIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return TrainingSession::query()
            ->whereIn('client_id', $clientIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['client_id', 'date'])
            ->map(fn (TrainingSession $session) => $session->client_id.'|'.$session->date->toDateString());
    }

    /**
     * @param  Collection<int, Client>  $roster
     * @param  array<int, list<string>>  $aliases
     */
    private function match(CalendarEvent $event, Collection $roster, array $aliases): ?SessionCandidate
    {
        $words = $this->words($event->title);

        $hits = $roster->filter(
            fn (Client $client) => $this->matches($words, $aliases[$client->getKey()] ?? [])
        )->values();

        return match ($hits->count()) {
            1 => new SessionCandidate($event, $hits->first()),
            0 => new SessionCandidate($event, null),
            // Two cards answer to the same title. The trainer picks; guessing here
            // would put the money on a coin toss.
            default => new SessionCandidate($event, null, $hits->all()),
        };
    }

    /**
     * Whole-word matching, so "bogucka" never catches "bogucki" and a two-word alias
     * only counts when both its words are in the title (docs/AGENT-API.md §4).
     *
     * @param  list<string>  $words
     * @param  list<string>  $aliases
     */
    private function matches(array $words, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            $parts = explode(' ', $alias);

            if (count(array_intersect($parts, $words)) === count($parts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function words(string $title): array
    {
        $normalised = preg_replace('/\s+/u', ' ', trim(mb_strtolower(
            (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title), 'UTF-8'
        )));

        return $normalised === '' ? [] : explode(' ', $normalised);
    }
}
