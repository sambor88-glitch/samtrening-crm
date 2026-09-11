<?php

use App\Support\DateRange;
use Illuminate\Support\Carbon;

test('a month prefix covers the whole month in the studio time zone', function () {
    $range = DateRange::fromPrefix('2026-09');

    expect($range->isYear())->toBeFalse()
        ->and($range->start()->format('Y-m-d H:i:s P'))->toBe('2026-09-01 00:00:00 +02:00')
        ->and($range->end()->format('Y-m-d H:i:s P'))->toBe('2026-09-30 23:59:59 +02:00')
        ->and($range->firstDay())->toBe('2026-09-01')
        ->and($range->lastDay())->toBe('2026-09-30');
});

test('a month crossing the end of summer time still ends at local midnight', function () {
    $range = DateRange::fromPrefix('2026-10');

    expect($range->start()->format('Y-m-d H:i:s P'))->toBe('2026-10-01 00:00:00 +02:00')
        ->and($range->end()->format('Y-m-d H:i:s P'))->toBe('2026-10-31 23:59:59 +01:00');
});

test('a year prefix covers the whole year', function () {
    $range = DateRange::fromPrefix('2026');

    expect($range->isYear())->toBeTrue()
        ->and($range->start()->format('Y-m-d H:i:s P'))->toBe('2026-01-01 00:00:00 +01:00')
        ->and($range->firstDay())->toBe('2026-01-01')
        ->and($range->lastDay())->toBe('2026-12-31');
});

test('the current month follows the Warsaw calendar, not UTC', function () {
    $this->travelTo(Carbon::parse('2026-09-30 22:30:00', 'UTC')); // already 1 October in Warsaw

    expect(DateRange::currentMonth()->prefix())->toBe('2026-10');
});

test('rejects anything that is not a month or a year', function (string $prefix) {
    DateRange::fromPrefix($prefix);
})->throws(InvalidArgumentException::class)->with(['', '2026-13', '2026-9', '26-09', '2026-09-01']);
