<?php

namespace App\Domain\Messaging;

use App\Domain\Clients\Models\Client;
use RuntimeException;

/**
 * A message that must not even be attempted. Both cases would send something embarrassing: a
 * request to pay BLIK to "—", or a text to nobody.
 */
class SmsNotPossible extends RuntimeException
{
    public static function withoutBlikNumber(): self
    {
        return new self('Nie masz numeru BLIK w Ustawieniach — bez niego SMS poszedłby z pustym numerem.');
    }

    public static function withoutPhone(Client $client): self
    {
        return new self($client->name.' nie ma numeru telefonu na karcie. Uzupełnij go w edycji karty.');
    }
}
