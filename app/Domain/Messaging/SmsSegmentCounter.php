<?php

namespace App\Domain\Messaging;

/**
 * A single "ą" pushes the whole message from GSM-7 to UCS-2 and cuts what fits from 160
 * characters to 70 — the same text can suddenly cost three messages instead of one, which is
 * why this counter sits under every template (docs/START-TUTAJ.md §9).
 */
class SmsSegmentCounter
{
    private const string POLISH = '/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u';

    public function count(string $text): SmsCount
    {
        $length = mb_strlen($text);
        $unicode = preg_match(self::POLISH, $text) === 1;

        $single = $unicode ? 70 : 160;
        $concatenated = $unicode ? 67 : 153;

        return new SmsCount(
            characters: $length,
            segments: $length <= $single ? 1 : (int) ceil($length / $concatenated),
            encoding: $unicode ? 'UCS-2' : 'GSM-7',
        );
    }
}
