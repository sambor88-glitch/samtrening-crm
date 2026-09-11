<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Komunikaty walidacji
    |--------------------------------------------------------------------------
    |
    | Tylko reguły, których używamy. Brakujące klucze lecą na angielski plik
    | frameworka — jeśli zobaczysz angielski komunikat, dopisz tu polski.
    |
    */

    'accepted' => 'Trzeba zaznaczyć :attribute.',
    'after' => 'Pole :attribute musi być datą po :date.',
    'after_or_equal' => 'Pole :attribute musi być datą nie wcześniejszą niż :date.',
    'before' => 'Pole :attribute musi być datą przed :date.',
    'before_or_equal' => 'Pole :attribute musi być datą nie późniejszą niż :date.',
    'boolean' => 'Pole :attribute musi być prawdą albo fałszem.',
    'confirmed' => 'Powtórzenie pola :attribute nie zgadza się.',
    'current_password' => 'Hasło jest nieprawidłowe.',
    'date' => 'Pole :attribute nie jest poprawną datą.',
    'digits' => 'Pole :attribute musi mieć :digits cyfr.',
    'digits_between' => 'Pole :attribute musi mieć od :min do :max cyfr.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'exists' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'file' => 'Pole :attribute musi być plikiem.',
    'image' => 'Pole :attribute musi być obrazem.',
    'in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'max' => [
        'array' => 'Pole :attribute nie może mieć więcej niż :max elementów.',
        'file' => 'Pole :attribute nie może być większe niż :max kilobajtów.',
        'numeric' => 'Pole :attribute nie może być większe niż :max.',
        'string' => 'Pole :attribute nie może mieć więcej niż :max znaków.',
    ],
    'mimes' => 'Pole :attribute musi być plikiem typu: :values.',
    'min' => [
        'array' => 'Pole :attribute musi mieć co najmniej :min elementów.',
        'file' => 'Pole :attribute musi mieć co najmniej :min kilobajtów.',
        'numeric' => 'Pole :attribute nie może być mniejsze niż :min.',
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
    ],
    'numeric' => 'Pole :attribute musi być liczbą.',
    'required' => 'Pole :attribute jest wymagane.',
    'required_if' => 'Pole :attribute jest wymagane, gdy :other to :value.',
    'same' => 'Pole :attribute i :other muszą być takie same.',
    'string' => 'Pole :attribute musi być tekstem.',
    'unique' => 'Taki :attribute już u nas jest.',
    'uploaded' => 'Nie udało się wgrać pliku :attribute.',
    'url' => 'Pole :attribute musi być poprawnym adresem.',

    'attributes' => [
        'email' => 'adres e-mail',
        'name' => 'imię i nazwisko',
        'password' => 'hasło',
        'phone' => 'telefon',
        'price' => 'kwota',
        'rate' => 'stawka',
    ],

];
