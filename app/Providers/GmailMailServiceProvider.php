<?php

namespace App\Providers;

use App\Mail\Gmail\GmailAccessToken;
use App\Mail\Gmail\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the "gmail" mail transport — see config/mail.php and SC-17.
 */
class GmailMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('gmail', function (array $config) {
            return new GmailApiTransport(new GmailAccessToken($config), $config);
        });
    }
}
