<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Komunikaty resetu hasła
    |--------------------------------------------------------------------------
    |
    | „sent", „throttled" i „user" nigdy nie trafiają na ekran prośby o link —
    | odpowiedź jest tam zawsze taka sama, żeby nie dało się sprawdzić, kto ma
    | konto w studiu. Zostają dla ekranu ustawiania hasła i dla logów.
    |
    */

    'reset' => 'Hasło zmienione. Zaloguj się nowym.',
    'sent' => 'Link do ustawienia hasła poleciał na podany adres.',
    'throttled' => 'Przed kolejną próbą trzeba chwilę odczekać.',
    'token' => 'Ten link jest nieważny albo został już użyty. Poproś o nowy.',
    'user' => 'Nie znamy tego adresu.',

];
