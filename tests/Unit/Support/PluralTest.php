<?php

use App\Support\Plural;

test('picks the Polish plural form', function (int $n, string $expected) {
    expect(Plural::of($n, 'sesja', 'sesje', 'sesji'))->toBe($expected);
})->with([
    [0, '0 sesji'],
    [1, '1 sesja'],
    [2, '2 sesje'],
    [4, '4 sesje'],
    [5, '5 sesji'],
    [12, '12 sesji'],
    [14, '14 sesji'],
    [21, '21 sesji'],
    [22, '22 sesje'],
    [25, '25 sesji'],
    [112, '112 sesji'],
    [122, '122 sesje'],
]);

test('returns the form alone for labels such as "Wyślij 3 podsumowania"', function () {
    expect(Plural::word(3, 'podsumowanie', 'podsumowania', 'podsumowań'))->toBe('podsumowania')
        ->and(Plural::word(5, 'podsumowanie', 'podsumowania', 'podsumowań'))->toBe('podsumowań');
});
