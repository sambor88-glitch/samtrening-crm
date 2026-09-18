<?php

use App\Domain\Calendar\CalendarAccessToken;
use App\Domain\Calendar\GoogleCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function calendar(array $overrides = []): GoogleCalendar
{
    $config = array_merge([
        'client_id' => 'id', 'client_secret' => 'sekret', 'refresh_token' => 'refresh',
        'calendar_id' => 'primary',
        'endpoints' => [
            'token' => 'https://oauth2.googleapis.com/token',
            'events' => 'https://www.googleapis.com/calendar/v3/calendars/:calendar/events',
        ],
        'token_leeway' => 60, 'timeout' => 15,
    ], $overrides);

    return new GoogleCalendar(new CalendarAccessToken($config), $config);
}

/** One entry as Google returns it. */
function googleEvent(string $title, string $start, array $extra = []): array
{
    return array_merge(['id' => md5($title.$start), 'summary' => $title, 'status' => 'confirmed',
        'start' => ['dateTime' => $start]], $extra);
}

function fakeCalendar(array $items, array $extra = []): void
{
    Cache::put(CalendarAccessToken::CACHE_KEY, 'dostep', 3600);
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(array_merge(['items' => $items], $extra))]);
}

$week = fn () => [CarbonImmutable::parse('2026-09-14'), CarbonImmutable::parse('2026-09-20')];

test('trainings come back as events, oldest first', function () use ($week) {
    fakeCalendar([
        googleEvent('Kasia Bogucka', '2026-09-17T09:00:00+02:00'),
        googleEvent('Anna trening', '2026-09-15T10:00:00+02:00'),
    ]);

    $events = calendar()->between(...$week());

    expect($events)->toHaveCount(2)
        ->and($events[0]->title)->toBe('Anna trening')
        ->and($events[0]->date())->toBe('2026-09-15')
        ->and($events[1]->title)->toBe('Kasia Bogucka');
});

test('recurring trainings are expanded into single occurrences', function () use ($week) {
    // Without singleEvents a weekly training answers as one rule, not as the dozen
    // sessions actually trained — and the studio trains on repeats.
    fakeCalendar([googleEvent('Anna trening', '2026-09-15T10:00:00+02:00')]);

    calendar()->between(...$week());

    Http::assertSent(fn (Request $r) => $r['singleEvents'] === 'true' && $r['orderBy'] === 'startTime');
});

test('the range asked for is the range sent', function () use ($week) {
    fakeCalendar([]);

    calendar()->between(...$week());

    Http::assertSent(fn (Request $r) => str_starts_with($r['timeMin'], '2026-09-14')
        && str_starts_with($r['timeMax'], '2026-09-20'));
});

test('a cancelled slot is not a training', function () use ($week) {
    fakeCalendar([
        googleEvent('Anna trening', '2026-09-15T10:00:00+02:00', ['status' => 'cancelled']),
        googleEvent('Kasia Bogucka', '2026-09-16T10:00:00+02:00'),
    ]);

    expect(calendar()->between(...$week())->pluck('title')->all())->toBe(['Kasia Bogucka']);
});

test('a whole-day entry is never offered as a session', function () use ($week) {
    // "Urlop" across a week is not a training, whatever it is called.
    fakeCalendar([
        ['id' => 'x', 'summary' => 'Urlop', 'status' => 'confirmed', 'start' => ['date' => '2026-09-15']],
        googleEvent('Anna trening', '2026-09-16T10:00:00+02:00'),
    ]);

    expect(calendar()->between(...$week())->pluck('title')->all())->toBe(['Anna trening']);
});

test('an entry with no title has nothing to match a client on', function () use ($week) {
    fakeCalendar([
        ['id' => 'x', 'status' => 'confirmed', 'start' => ['dateTime' => '2026-09-15T10:00:00+02:00']],
        googleEvent('Anna trening', '2026-09-16T10:00:00+02:00'),
    ]);

    expect(calendar()->between(...$week()))->toHaveCount(1);
});

test('a late training keeps its own day, in the studio time zone', function () use ($week) {
    // 23:30 in Warsaw is already tomorrow in UTC; the day must not slide.
    fakeCalendar([googleEvent('Anna trening', '2026-09-15T23:30:00+02:00')]);

    expect(calendar()->between(...$week())[0]->date())->toBe('2026-09-15');
});

test('a second page is followed', function () use ($week) {
    Cache::put(CalendarAccessToken::CACHE_KEY, 'dostep', 3600);
    Http::fakeSequence()
        ->push(['items' => [googleEvent('Anna trening', '2026-09-15T10:00:00+02:00')], 'nextPageToken' => 'strona-2'])
        ->push(['items' => [googleEvent('Kasia Bogucka', '2026-09-16T10:00:00+02:00')]]);

    expect(calendar()->between(...$week()))->toHaveCount(2);
});

test('a revoked token is refreshed once and the call retried', function () use ($week) {
    Cache::put(CalendarAccessToken::CACHE_KEY, 'odwolany', 3600);
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'swiezy', 'expires_in' => 3600]),
        'www.googleapis.com/calendar/*' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['items' => [googleEvent('Anna trening', '2026-09-15T10:00:00+02:00')]]),
    ]);

    expect(calendar()->between(...$week()))->toHaveCount(1);

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer swiezy'));
});

test('a refusal that is not about the token is reported, not swallowed', function () use ($week) {
    Cache::put(CalendarAccessToken::CACHE_KEY, 'dostep', 3600);
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['error' => 'backendError'], 500)]);

    expect(fn () => calendar()->between(...$week()))->toThrow(RuntimeException::class, '500');
});

test('a shared studio calendar is addressed by its id', function () use ($week) {
    fakeCalendar([]);

    calendar(['calendar_id' => 'studio@group.calendar.google.com'])->between(...$week());

    Http::assertSent(fn (Request $r) => str_contains($r->url(), rawurlencode('studio@group.calendar.google.com')));
});
