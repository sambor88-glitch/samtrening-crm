<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Amounts live in integer grosze (1/100 PLN). This class only converts them for display and
 * for form inputs — no arithmetic on money belongs in Blade.
 */
class Money
{
    /**
     * 20000 → "200 zł", 125000 → "1 250 zł", 19950 → "199,50 zł" (grouping as in the prototype).
     */
    public static function format(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';
        $grosze = abs($grosze);
        $rest = $grosze % 100;

        return $sign
            .number_format(intdiv($grosze, 100), 0, ',', ' ')
            .($rest > 0 ? ','.str_pad((string) $rest, 2, '0', STR_PAD_LEFT) : '')
            .' zł';
    }

    /**
     * An amount typed into a form, in złoty ("200", "199,50", "1 250.5") → grosze.
     *
     * @throws InvalidArgumentException when the value is not a non-negative amount
     */
    public static function fromInput(string|int $zloty): int
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) $zloty));

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException("Not an amount: {$zloty}");
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    /**
     * Grosze → value for an <input type="number">: 20000 → "200", 19950 → "199.50".
     */
    public static function toInput(int $grosze): string
    {
        $rest = $grosze % 100;

        return intdiv($grosze, 100).($rest === 0 ? '' : '.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
    }
}
