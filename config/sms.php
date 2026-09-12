<?php

use App\Domain\Messaging\Providers\LogSmsProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Dostawca SMS
    |--------------------------------------------------------------------------
    |
    | Dopóki studio nie wybierze dostawcy (SC-16), wszystko idzie do logu i nikt
    | nie dostaje prawdziwych wiadomości. Dołożenie dostawcy to jedna klasa
    | implementująca Messaging\Providers\SmsProvider i jeden wpis poniżej.
    |
    */

    'provider' => env('SMS_PROVIDER', 'log'),

    'providers' => [
        'log' => LogSmsProvider::class,
    ],

    /*
    | Nazwa nadawcy widoczna w telefonie klienta. Też do ustalenia — u operatorów
    | wymaga zgłoszenia, więc zostaje pusta, a lokalny dostawca to odnotowuje.
    */

    'sender' => env('SMS_SENDER'),

    'log_channel' => env('SMS_LOG_CHANNEL', 'stack'),

];
