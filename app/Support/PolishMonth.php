<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Month names in the cases the product needs: "Wrzesień" as a heading, "we wrześniu" in a
 * sentence, "podsumowanie września" in a subject line. Written out rather than taken from
 * Carbon, which knows only the genitive and calls it a format.
 */
class PolishMonth
{
    /** @var array<int, string> */
    private const array NOMINATIVE = [
        1 => 'styczeń',
        'luty',
        'marzec',
        'kwiecień',
        'maj',
        'czerwiec',
        'lipiec',
        'sierpień',
        'wrzesień',
        'październik',
        'listopad',
        'grudzień',
    ];

    /** @var array<int, string> */
    private const array GENITIVE = [
        1 => 'stycznia',
        'lutego',
        'marca',
        'kwietnia',
        'maja',
        'czerwca',
        'lipca',
        'sierpnia',
        'września',
        'października',
        'listopada',
        'grudnia',
    ];

    /** @var array<int, string> */
    private const array LOCATIVE = [
        1 => 'styczniu',
        'lutym',
        'marcu',
        'kwietniu',
        'maju',
        'czerwcu',
        'lipcu',
        'sierpniu',
        'wrześniu',
        'październiku',
        'listopadzie',
        'grudniu',
    ];

    /**
     * "Wrzesień" — a heading.
     */
    public static function name(CarbonInterface $month): string
    {
        return Str::ucfirst(self::accusative($month));
    }

    /**
     * "Wrzesień 2026" — a month that needs its year.
     */
    public static function withYear(CarbonInterface $month): string
    {
        return self::name($month).' '.$month->format('Y');
    }

    /**
     * "wrzesień" — "do zapłaty za wrzesień".
     */
    public static function accusative(CarbonInterface $month): string
    {
        return self::NOMINATIVE[self::index($month)];
    }

    /**
     * "września" — "podsumowanie września", "sesje z września".
     */
    public static function genitive(CarbonInterface $month): string
    {
        return self::GENITIVE[self::index($month)];
    }

    /**
     * "wrześniu" — the bare locative, for a sentence that brings its own preposition.
     */
    public static function locative(CarbonInterface $month): string
    {
        return self::LOCATIVE[self::index($month)];
    }

    /**
     * "we wrześniu" — only September takes "we"; the rest take "w".
     */
    public static function inMonth(CarbonInterface $month): string
    {
        $preposition = self::index($month) === 9 ? 'we ' : 'w ';

        return $preposition.self::locative($month);
    }

    private static function index(CarbonInterface $month): int
    {
        return (int) $month->format('n');
    }
}
