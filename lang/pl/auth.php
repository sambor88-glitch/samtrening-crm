<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Komunikaty uwierzytelniania
    |--------------------------------------------------------------------------
    |
    | Komunikaty logowania, które zależą od konta (nieznany adres, blokada),
    | siedzą w App\Http\Requests\Auth\LoginRequest — patrz docs/SPEC-EKRANY.md,
    | ekran 1. Tutaj zostaje to, po co sięga sam framework.
    |
    */

    'failed' => 'Hasło nie pasuje do tego adresu.',
    'password' => 'Hasło jest nieprawidłowe.',
    'throttle' => 'Za dużo prób logowania. Spróbuj ponownie za :seconds s.',

];
