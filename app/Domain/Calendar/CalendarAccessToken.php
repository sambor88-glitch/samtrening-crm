<?php

namespace App\Domain\Calendar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Trades the calendar's refresh token for a short-lived access token, and caches it
 * for the hour it lives — otherwise every look at the diary would cost a round trip
 * to Google first.
 *
 * This deliberately duplicates `Mail\Gmail\GmailAccessToken` rather than sharing a
 * base class with it. The two hold different credentials on purpose (see
 * config/calendar.php), and folding them together would put the studio's mail on the
 * same code path as a screen nobody needs in order to send a reminder. Sixty lines
 * repeated is the cheaper half of that trade; if a third Google client ever appears,
 * that is the moment to extract one.
 */
class CalendarAccessToken
{
    public const string CACHE_KEY = 'calendar.access_token';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function value(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetch();
    }

    /**
     * Forces a fresh token past the cache — for when Google answers 401, which it
     * does when consent was withdrawn before the token's stated expiry.
     */
    public function refresh(): string
    {
        Cache::forget(self::CACHE_KEY);

        return $this->fetch();
    }

    /**
     * Whether the calendar is configured at all. The screen asks first, so an
     * unconfigured studio sees an explanation rather than an exception.
     */
    public function isConfigured(): bool
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (blank($this->config[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function fetch(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Kalendarz Google nie jest skonfigurowany. Ustaw GOOGLE_CALENDAR_REFRESH_TOKEN '
                .'i uruchom php artisan calendar:authorize.'
            );
        }

        $response = Http::asForm()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->post($this->config['endpoints']['token'], [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'refresh_token' => $this->config['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Nie udało się odświeżyć tokenu kalendarza: '.$this->describeError($response->json(), $response->body())
            );
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google nie zwrócił tokenu dostępu do kalendarza.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        $leeway = (int) ($this->config['token_leeway'] ?? 60);

        Cache::put(self::CACHE_KEY, $token, max(30, $expiresIn - $leeway));

        return $token;
    }

    private function describeError(mixed $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        $parts = array_filter([
            is_string($payload['error'] ?? null) ? $payload['error'] : null,
            is_string($payload['error_description'] ?? null) ? $payload['error_description'] : null,
        ]);

        return $parts === [] ? $fallback : implode(' — ', $parts);
    }
}
