<?php

use App\Support\Money;

test('formats grosze the way the prototype shows złoty', function (int $grosze, string $expected) {
    expect(Money::format($grosze))->toBe($expected);
})->with([
    [0, '0 zł'],
    [20000, '200 zł'],
    [125000, '1 250 zł'],
    [1250000, '12 500 zł'],
    [19950, '199,50 zł'],
    [5, '0,05 zł'],
]);

test('reads amounts typed into forms', function (string|int $input, int $grosze) {
    expect(Money::fromInput($input))->toBe($grosze);
})->with([
    ['200', 20000],
    [200, 20000],
    ['199,50', 19950],
    ['199.5', 19950],
    ['1 250', 125000],
    [' 80 ', 8000],
]);

test('rejects values that are not amounts', function (string $input) {
    Money::fromInput($input);
})->throws(InvalidArgumentException::class)->with(['', 'abc', '-50', '12,345', '1.2.3']);

test('prepares values for number inputs', function () {
    expect(Money::toInput(20000))->toBe('200')
        ->and(Money::toInput(19950))->toBe('199.50')
        ->and(Money::toInput(5))->toBe('0.05');
});
