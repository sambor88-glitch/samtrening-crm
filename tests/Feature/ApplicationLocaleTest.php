<?php

test('the application runs in the Warsaw time zone with the Polish locale', function () {
    expect(config('app.timezone'))->toBe('Europe/Warsaw')
        ->and(now()->timezoneName)->toBe('Europe/Warsaw')
        ->and(app()->getLocale())->toBe('pl');
});
