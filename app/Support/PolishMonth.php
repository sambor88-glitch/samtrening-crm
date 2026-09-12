<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Month names in the two cases the screens need: "Wrzesień" as a heading and "we wrześniu" in a
 * sentence. Written out rather than taken from Carbon's locale — Carbon gives the genitive
 * ("września", the form used inside a date) and nothing at all for the locative.
 */
class PolishMonth
{
    /** @var array<int, string> */
    private const array NAMES = [
        1 => 'Styczeń',
        'Luty',
        'Marzec',
        'Kwiecień',
        'Maj',
        'Czerwiec',
        'Lipiec',
        'Sierpień',
        'Wrzesień',
        'Październik',
        'Listopad',
        'Grudzień',
    ];

    /** @var array<int, string> */
    private const array IN_MONTH = [
        1 => 'w styczniu',
        'w lutym',
        'w marcu',
        'w kwietniu',
        'w maju',
        'w czerwcu',
        'w lipcu',
        'w sierpniu',
        'we wrześniu',
        'w październiku',
        'w listopadzie',
        'w grudniu',
    ];

    /**
     * "Wrzesień" — the month on its own.
     */
    public static function name(CarbonInterface $month): string
    {
        return self::NAMES[(int) $month->format('n')];
    }

    /**
     * "Wrzesień 2026" — a month that needs its year.
     */
    public static function withYear(CarbonInterface $month): string
    {
        return self::name($month).' '.$month->format('Y');
    }

    /**
     * "we wrześniu" — a month inside a sentence.
     */
    public static function inMonth(CarbonInterface $month): string
    {
        return self::IN_MONTH[(int) $month->format('n')];
    }
}
