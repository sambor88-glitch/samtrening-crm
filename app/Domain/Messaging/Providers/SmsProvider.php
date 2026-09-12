<?php

namespace App\Domain\Messaging\Providers;

/**
 * Every SMS leaves through here — docs/START-TUTAJ.md §4, rule 4. Which company carries it is a
 * question for one class, not for the whole app (SC-16 is still open).
 */
interface SmsProvider
{
    /**
     * @throws \RuntimeException when the carrier refuses or cannot be reached
     */
    public function send(string $phone, string $text): void;
}
