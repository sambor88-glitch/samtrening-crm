<?php

namespace App\Mail\Gmail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Trades the long-lived refresh token for a short-lived access token.
 *
 * The access token lives an hour, so it is cached: otherwise every queued
 * message would pay an extra round trip to Google before it could be sent.
 */
class GmailAccessToken
{
    public const CACHE_KEY = 'gmail.access_token';

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
     * Forces a fresh token, bypassing the cache. Used when Google answers 401 —
     * a token can be revoked before its stated expiry.
     */
    public function refresh(): string
    {
        Cache::forget(self::CACHE_KEY);

        return $this->fetch();
    }

    private function fetch(): string
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (blank($this->config[$key] ?? null)) {
                throw new RuntimeException(
                    "Gmail API is not configured: {$key} is missing. Set the GMAIL_* variables and run php artisan gmail:authorize."
                );
            }
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
                'Could not refresh the Gmail API token: '.$this->describeError($response->json(), $response->body())
            );
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google returned no access token.');
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
