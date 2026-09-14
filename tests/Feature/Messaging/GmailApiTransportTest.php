<?php

use App\Mail\Gmail\GmailAccessToken;
use App\Mail\Gmail\GmailApiTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * The CRM reaches Gmail over HTTPS because DigitalOcean blocks outbound SMTP —
 * SC-17. These cover the parts that fail silently in production if they drift.
 */
function gmailTransportConfig(array $overrides = []): array
{
    return array_merge([
        'transport' => 'gmail',
        'client_id' => 'id',
        'client_secret' => 'secret',
        'refresh_token' => 'refresh',
        'send_as' => 'maciej.samborski@samtrening.com',
        'redirect_uri' => 'http://localhost',
        'scope' => 'https://www.googleapis.com/auth/gmail.send',
        'endpoints' => [
            'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'send' => 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        ],
        'token_leeway' => 60,
        'timeout' => 15,
    ], $overrides);
}

function gmailTransport(array $overrides = []): GmailApiTransport
{
    $config = gmailTransportConfig($overrides);

    return new GmailApiTransport(new GmailAccessToken($config), $config);
}

function gmailMessage(): Email
{
    return (new Email)
        ->from('maciej.samborski@samtrening.com')
        ->to('klientka@example.com')
        ->subject('Podsumowanie września')
        ->text('Treść');
}

beforeEach(function () {
    Cache::forget(GmailAccessToken::CACHE_KEY);
});

it('sends the message to the Gmail API as url-safe base64', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600]),
        'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1']),
    ]);

    gmailTransport()->send(gmailMessage());

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'gmail.googleapis.com')) {
            return false;
        }

        $raw = $request->data()['raw'] ?? '';

        // Plain base64 is rejected by Gmail — the alphabet and the padding matter.
        expect($raw)->not->toContain('+')
            ->and($raw)->not->toContain('/')
            ->and($raw)->not->toContain('=');

        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        return is_string($decoded)
            && str_contains($decoded, 'klientka@example.com')
            && $request->hasHeader('Authorization', 'Bearer abc123');
    });
});

it('fetches the access token once for several messages', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600]),
        'gmail.googleapis.com/*' => Http::response(['id' => 'msg-1']),
    ]);

    $transport = gmailTransport();
    $transport->send(gmailMessage());
    $transport->send(gmailMessage());

    $tokenCalls = 0;

    Http::assertSent(function (Request $request) use (&$tokenCalls) {
        if (str_contains($request->url(), 'oauth2.googleapis.com')) {
            $tokenCalls++;
        }

        return true;
    });

    expect($tokenCalls)->toBe(1);
});

it('retries once with a fresh token when Google answers 401', function () {
    Cache::put(GmailAccessToken::CACHE_KEY, 'revoked', 3600);

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
        'gmail.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Invalid Credentials']], 401)
            ->push(['id' => 'msg-1'], 200),
    ]);

    gmailTransport()->send(gmailMessage());

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fresh'));
});

it('raises a transport exception carrying Google\'s message', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'abc123', 'expires_in' => 3600]),
        'gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Delegation denied']], 403),
    ]);

    gmailTransport()->send(gmailMessage());
})->throws(TransportException::class, 'Delegation denied');

it('refuses to send when the refresh token is missing', function () {
    Http::fake();

    gmailTransport(['refresh_token' => null])->send(gmailMessage());
})->throws(RuntimeException::class, 'refresh_token');
