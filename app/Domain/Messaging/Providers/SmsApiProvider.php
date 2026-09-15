<?php

namespace App\Domain\Messaging\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * SMSAPI — the carrier chosen in SC-16. Prepaid, so a quiet month costs nothing, and the sender
 * name is free once the operator has approved it.
 *
 * One POST, no SDK: the whole contract is four parameters and a JSON answer, and a library would
 * only add a dependency to keep current.
 */
class SmsApiProvider implements SmsProvider
{
    public function send(string $phone, string $text): void
    {
        $token = config('sms.smsapi.token');
        $sender = config('sms.sender');

        if (blank($token)) {
            throw new RuntimeException('Brak SMS_API_TOKEN — SMSAPI nie ma czym się uwierzytelnić.');
        }

        if (blank($sender)) {
            // Without an approved name SMSAPI would either refuse or bill an ECO message from a
            // random number, and the client would get a text from a stranger.
            throw new RuntimeException('Brak SMS_SENDER — nazwa nadawcy musi być zatwierdzona przez operatora.');
        }

        try {
            $response = Http::withToken($token)
                ->asForm()
                ->timeout((int) config('sms.smsapi.timeout', 15))
                ->post(config('sms.smsapi.url'), [
                    'to' => self::normalise($phone),
                    'message' => $text,
                    'from' => $sender,
                    'format' => 'json',
                    // Polish diacritics travel as they are; the operator bills them as UCS-2,
                    // which SmsSegmentCounter already accounts for.
                    'encoding' => 'utf-8',
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Nie udało się połączyć z SMSAPI: '.$e->getMessage(), 0, $e);
        }

        $payload = $response->json();

        // SMSAPI answers 200 with an error body, so the status code alone proves nothing.
        if (is_array($payload) && isset($payload['error'])) {
            throw new RuntimeException(
                'SMSAPI odmówił ('.$payload['error'].'): '.($payload['message'] ?? 'bez treści')
            );
        }

        if ($response->failed()) {
            throw new RuntimeException('SMSAPI odpowiedział błędem HTTP '.$response->status().'.');
        }

        if (! is_array($payload) || ($payload['count'] ?? 0) < 1) {
            throw new RuntimeException('SMSAPI nie potwierdził przyjęcia wiadomości.');
        }
    }

    /**
     * Numbers are typed by hand into the client card, so they arrive as "+48 600 300 400" as
     * often as "600300400". SMSAPI wants digits with the country code.
     */
    private static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 9) {
            return '48'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '48')) {
            return $digits;
        }

        throw new RuntimeException('Numer "'.$phone.'" nie wygląda na polski numer telefonu.');
    }
}
