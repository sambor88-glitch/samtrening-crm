<?php

namespace App\Domain\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the studio's diary. Never writes to it — the token carries
 * `calendar.readonly` and nothing else (config/calendar.php).
 */
class GoogleCalendar
{
    /** Google caps a page at 2500; 250 keeps a single page enough for any real month. */
    private const int PAGE_SIZE = 250;

    /** A runaway loop would hammer Google; no studio has 2500 trainings in a month. */
    private const int MAX_PAGES = 10;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly CalendarAccessToken $token,
        private readonly array $config,
    ) {}

    public function isConfigured(): bool
    {
        return $this->token->isConfigured();
    }

    /**
     * Every training booked between the two days, both ends included.
     *
     * Cached for a few minutes because the screen behind this is Livewire: ticking a
     * checkbox is a fresh request, and paying a round trip to Google for each tick
     * would make the list feel broken. `forget()` is the way to ask again on purpose.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $ttl = (int) ($this->config['cache_seconds'] ?? 300);

        if ($ttl <= 0) {
            return $this->fetch($from, $to);
        }

        return Cache::remember(
            $this->cacheKey($from, $to),
            $ttl,
            fn () => $this->fetch($from, $to),
        );
    }

    /**
     * Drops the cached answer for a range, so the next look really asks Google.
     */
    public function forget(CarbonImmutable $from, CarbonImmutable $to): void
    {
        Cache::forget($this->cacheKey($from, $to));
    }

    private function cacheKey(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return 'calendar.events.'.md5(implode('|', [
            (string) $this->config['calendar_id'],
            $from->toDateString(),
            $to->toDateString(),
        ]));
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    private function fetch(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $timezone = config('app.timezone');
        $events = collect();
        $pageToken = null;
        $pages = 0;

        do {
            $response = $this->fetchPage($from, $to, $timezone, $pageToken);

            foreach ((array) $response->json('items', []) as $item) {
                if (is_array($item) && $event = CalendarEvent::fromGoogle($item, $timezone)) {
                    $events->push($event);
                }
            }

            $pageToken = $response->json('nextPageToken');
            $pages++;
        } while (is_string($pageToken) && $pageToken !== '' && $pages < self::MAX_PAGES);

        return $events->sortBy(fn (CalendarEvent $event) => $event->startsAt->getTimestamp())->values();
    }

    private function fetchPage(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?string $pageToken,
    ): Response {
        $response = $this->request($from, $to, $timezone, $pageToken, $this->token->value());

        // A token can be revoked before it expires; one retry with a fresh one tells
        // that apart from consent that is really gone.
        if ($response->status() === 401) {
            $response = $this->request($from, $to, $timezone, $pageToken, $this->token->refresh());
        }

        if ($response->failed()) {
            throw new RuntimeException('Kalendarz Google odmówił odpowiedzi: '.$response->status().' '.$response->body());
        }

        return $response;
    }

    private function request(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        ?string $pageToken,
        string $accessToken,
    ): Response {
        $url = str_replace(':calendar', rawurlencode((string) $this->config['calendar_id']), $this->config['endpoints']['events']);

        return Http::withToken($accessToken)
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->get($url, array_filter([
                'timeMin' => $from->startOfDay()->toRfc3339String(),
                'timeMax' => $to->endOfDay()->toRfc3339String(),
                // Without this a weekly training comes back as one rule rather than
                // as the dozen occurrences actually booked — and the studio trains
                // on repeats.
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'timeZone' => $timezone,
                'maxResults' => self::PAGE_SIZE,
                'pageToken' => $pageToken,
            ], fn ($value) => $value !== null && $value !== ''));
    }
}
