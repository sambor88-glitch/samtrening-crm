<?php

use App\Domain\Calendar\CalendarAccessToken;
use App\Mail\Gmail\GmailAccessToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function calendarConfig(array $overrides = []): array
{
    return array_merge([
        'client_id' => 'id-kalendarza',
        'client_secret' => 'sekret',
        'refresh_token' => 'refresh-kalendarza',
        'endpoints' => ['token' => 'https://oauth2.googleapis.com/token'],
        'token_leeway' => 60,
        'timeout' => 15,
    ], $overrides);
}

beforeEach(function () {
    Cache::forget(CalendarAccessToken::CACHE_KEY);
});

test('a refresh token is traded for an access token', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'dostep-123', 'expires_in' => 3600])]);

    expect((new CalendarAccessToken(calendarConfig()))->value())->toBe('dostep-123');

    Http::assertSent(fn (Request $r) => $r['refresh_token'] === 'refresh-kalendarza'
        && $r['grant_type'] === 'refresh_token');
});

test('the access token is cached, so a second look costs no round trip', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'dostep-123', 'expires_in' => 3600])]);

    $token = new CalendarAccessToken(calendarConfig());
    $token->value();
    $token->value();

    Http::assertSentCount(1);
});

test('refresh() goes past the cache', function () {
    Cache::put(CalendarAccessToken::CACHE_KEY, 'stary', 3600);
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'nowy', 'expires_in' => 3600])]);

    expect((new CalendarAccessToken(calendarConfig()))->refresh())->toBe('nowy');
});

test('the calendar token is kept apart from the one Gmail uses', function () {
    // docs/START-TUTAJ.md §9: a failure in one channel must not take another down.
    expect(CalendarAccessToken::CACHE_KEY)->not->toBe(GmailAccessToken::CACHE_KEY);
});

test('a studio without a calendar says so instead of throwing mid-screen', function () {
    expect((new CalendarAccessToken(calendarConfig(['refresh_token' => null])))->isConfigured())->toBeFalse()
        ->and((new CalendarAccessToken(calendarConfig()))->isConfigured())->toBeTrue();
});

test('asking for a token without configuration names what is missing', function () {
    $token = new CalendarAccessToken(calendarConfig(['refresh_token' => '']));

    expect(fn () => $token->value())
        ->toThrow(RuntimeException::class, 'GOOGLE_CALENDAR_REFRESH_TOKEN');
});

test('a refusal from Google is reported with its reason', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response([
        'error' => 'invalid_grant',
        'error_description' => 'Token has been expired or revoked.',
    ], 400)]);

    expect(fn () => (new CalendarAccessToken(calendarConfig()))->value())
        ->toThrow(RuntimeException::class, 'invalid_grant');
});
