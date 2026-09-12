<?php

namespace App\Domain\Messaging;

use App\Domain\Clients\Models\Client;
use RuntimeException;

/**
 * A message that must not even be attempted, because it would go out wrong or nowhere: a request
 * to pay BLIK to "—", a text to a client with no phone, a statement to a client with no e-mail.
 */
class MessageNotPossible extends RuntimeException
{
    public static function withoutBlikNumber(): self
    {
        return new self('Nie masz numeru BLIK w Ustawieniach — bez niego SMS poszedłby z pustym numerem.');
    }

    public static function withoutPhone(Client $client): self
    {
        return new self($client->name.' nie ma numeru telefonu na karcie. Uzupełnij go w edycji karty.');
    }

    public static function withoutEmail(Client $client): self
    {
        return new self($client->name.' nie ma adresu e-mail na karcie. Uzupełnij go w edycji karty.');
    }
}
