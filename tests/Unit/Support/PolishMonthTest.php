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
