<?php

namespace App\Support;

/**
 * Polish plural forms: 1 sesja, 2–4 sesje (but 12–14 sesji), 5+ sesji. One helper for the whole app.
 */
class Plural
{
    /**
     * of(1, 'sesja', 'sesje', 'sesji') → "1 sesja"; of(3, …) → "3 sesje"; of(5, …) → "5 sesji".
     */
    public static function of(int $n, string $one, string $few, string $many): string
    {
        return $n.' '.self::word($n, $one, $few, $many);
    }

    /**
     * The form alone, without the number.
     */
    public static function word(int $n, string $one, string $few, string $many): string
    {
        $last = $n % 10;
        $lastTwo = $n % 100;

        if ($n === 1) {
            return $one;
        }

        if ($last >= 2 && $last <= 4 && ($lastTwo < 10 || $lastTwo >= 20)) {
            return $few;
        }

        return $many;
    }
}
