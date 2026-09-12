<?php

namespace App\Domain\Training\Enums;

/**
 * What happened at the slot. Only `Completed` counts as a session held — a charged
 * cancellation is money, not a training (docs/START-TUTAJ.md §6).
 */
enum SessionKind: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * The Polish word for it — one vocabulary for the CSV, the RODO export and anywhere else
     * that has to name this in a sentence.
     */
    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Odbyta',
            self::Cancelled => 'Odwołana',
            self::NoShow => 'Nieobecność',
        };
    }
}
