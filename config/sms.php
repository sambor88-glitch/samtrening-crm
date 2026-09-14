<?php

use App\Domain\Messaging\Providers\LogSmsProvider;
use App\Domain\Messaging\Providers\SmsApiProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Dostawca SMS
    |--------------------------------------------------------------------------
    |
    | SC-16 rozstrzygnięte: SMSAPI, model prepaid — przy kilkudziesięciu monitach
    | miesięcznie abonament byłby karą za spokojny miesiąc. „log" zostaje dla
    | maszyny deweloperskiej i testów: zapisuje treść i nie wysyła nic.
    |
    */

    'provider' => env('SMS_PROVIDER', 'log'),

    'providers' => [
        'log' => LogSmsProvider::class,
        'smsapi' => SmsApiProvider::class,
    ],

    'smsapi' => [
        'token' => env('SMS_API_TOKEN'),
        'url' => env('SMS_API_URL', 'https://api.smsapi.pl/sms.do'),
        'timeout' => 15,
    ],

    /*
    | Nazwa nadawcy widoczna w telefonie klienta. Maksymalnie 11 znaków, bez
    | polskich znaków, zatwierdzana przez operatora. Pusta blokuje wysyłkę przez
    | SMSAPI, zamiast pozwolić wiadomości wyjść z przypadkowego numeru.
    */

    'sender' => env('SMS_SENDER'),

    'log_channel' => env('SMS_LOG_CHANNEL', 'stack'),

];
