<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Team\Models\User;

/**
 * Trainers rewrite these texts themselves, so every change is signed and dated — a message that
 * went out wrong should be traceable to the edit that made it wrong.
 */
class UpdateMessageTemplate
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $actor, string $key, string $body): MessageTemplate
    {
        $template = MessageTemplate::query()->where('key', $key)->firstOrFail();

        if ($template->body === $body) {
            return $template;
        }

        $template->update(['body' => $body]);

        $this->log->record($actor, 'Zmienił szablon wiadomości', $key);

        return $template;
    }
}
