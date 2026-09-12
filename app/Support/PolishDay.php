<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Polish weekday names, written out rather than taken from Carbon's locale — the same reason
 * `PolishMonth` exists: Carbon gives one form, and the grid header needs the short one.
 */
class PolishDay
{
    /** ISO day (1 = Monday) → the two-letter header the week grid uses. */
    private const array SHORT = [
        1 => 'Pn',
        2 => 'Wt',
        3 => 'Śr',
        4 => 'Cz',
        5 => 'Pt',
        6 => 'So',
        7 => 'Nd',
    ];

    /** ISO day → the full name, for titles and screen readers. */
    private const array FULL = [
        1 => 'poniedziałek',
        2 => 'wtorek',
        3 => 'środa',
        4 => 'czwartek',
        5 => 'piątek',
        6 => 'sobota',
        7 => 'niedziela',
    ];

    public static function short(CarbonInterface $day): string
    {
        return self::SHORT[$day->dayOfWeekIso];
    }

    public static function name(CarbonInterface $day): string
    {
        return self::FULL[$day->dayOfWeekIso];
    }
}
