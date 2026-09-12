<?php

namespace App\Domain\Messaging;

use App\Support\Plural;

/**
 * What one SMS text will actually cost: how long it is, how many messages that means and which
 * alphabet the operator has to use.
 */
readonly class SmsCount
{
    public function __construct(
        public int $characters,
        public int $segments,
        public string $encoding,
    ) {}

    /**
     * "148 znaków · 3 wiadomości · UCS-2"
     */
    public function label(): string
    {
        return $this->characters.' '.Plural::word($this->characters, 'znak', 'znaki', 'znaków')
            .' · '.$this->segments.' '.Plural::word($this->segments, 'wiadomość', 'wiadomości', 'wiadomości')
            .' · '.$this->encoding;
    }
}
