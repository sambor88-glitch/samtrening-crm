<?php

namespace App\Domain\Messaging\Providers;

use Illuminate\Support\Facades\Log;

/**
 * The local carrier: writes the message to the log and charges nobody. Nothing real goes out of
 * a developer's machine, and the studio can run the whole flow before a provider is chosen.
 */
class LogSmsProvider implements SmsProvider
{
    public function send(string $phone, string $text): void
    {
        Log::channel(config('sms.log_channel'))->info('SMS', [
            'do' => $phone,
            'nadawca' => config('sms.sender') ?: '(nadawca nieustalony)',
            'tresc' => $text,
        ]);
    }
}
