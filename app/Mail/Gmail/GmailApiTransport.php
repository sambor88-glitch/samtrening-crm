<?php

namespace App\Mail\Gmail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Throwable;

/**
 * Hands the finished MIME message to the Gmail API over HTTPS — SC-17.
 *
 * DigitalOcean blocks outbound SMTP (25, 465, 587) on every Droplet, so
 * Google's SMTP relay is unreachable from the app server. No Google client
 * library here on purpose: the integration is a single POST, and Google
 * applies the DKIM signature on its side.
 */
class GmailApiTransport extends AbstractTransport
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly GmailAccessToken $token,
        private readonly array $config,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $raw = self::base64url($message->toString());

        $response = $this->post($this->token->value(), $raw);

        // A 401 usually means the token was revoked early. One retry with a
        // fresh token, then the failure belongs to the queue.
        if ($response['status'] === 401) {
            $response = $this->post($this->token->refresh(), $raw);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new TransportException(
                'The Gmail API rejected the message ('.$response['status'].'): '.$response['error']
            );
        }
    }

    /**
     * @return array{status: int, error: string}
     */
    private function post(string $accessToken, string $raw): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->post($this->config['endpoints']['send'], ['raw' => $raw]);
        } catch (Throwable $e) {
            throw new TransportException('Could not reach the Gmail API: '.$e->getMessage(), 0, $e);
        }

        return [
            'status' => $response->status(),
            'error' => self::describeError($response->json(), $response->body()),
        ];
    }

    /**
     * The Gmail API expects URL-safe base64 with no padding.
     */
    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function describeError(mixed $payload, string $fallback): string
    {
        if (is_array($payload) && is_string($payload['error']['message'] ?? null)) {
            return $payload['error']['message'];
        }

        return $fallback !== '' ? $fallback : 'empty response body';
    }

    public function __toString(): string
    {
        return 'gmail-api://'.($this->config['send_as'] ?? 'me');
    }
}
