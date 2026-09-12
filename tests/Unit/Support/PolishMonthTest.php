<?php

use App\Support\PolishMonth;
use Carbon\CarbonImmutable;

test('a month knows how to stand on its own and how to sit in a sentence', function (string $date, string $name, string $inMonth) {
    $month = CarbonImmutable::parse($date);

    expect(PolishMonth::name($month))->toBe($name)
        ->and(PolishMonth::inMonth($month))->toBe($inMonth);
})->with([
    ['2026-01-15', 'Styczeń', 'w styczniu'],
    ['2026-07-01', 'Lipiec', 'w lipcu'],
    ['2026-09-30', 'Wrzesień', 'we wrześniu'],
    ['2026-11-02', 'Listopad', 'w listopadzie'],
]);

test('a month that needs its year gets it', function () {
    expect(PolishMonth::withYear(CarbonImmutable::parse('2026-09-12')))->toBe('Wrzesień 2026');
});

test('a month knows its other cases too', function (string $date, string $accusative, string $genitive, string $locative) {
    $month = CarbonImmutable::parse($date);

    expect(PolishMonth::accusative($month))->toBe($accusative)
        ->and(PolishMonth::genitive($month))->toBe($genitive)
        ->and(PolishMonth::locative($month))->toBe($locative);
})->with([
    ['2026-09-12', 'wrzesień', 'września', 'wrześniu'],
    ['2026-01-05', 'styczeń', 'stycznia', 'styczniu'],
    ['2026-05-05', 'maj', 'maja', 'maju'],
    ['2026-12-24', 'grudzień', 'grudnia', 'grudniu'],
]);
