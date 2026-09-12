<?php

namespace App\Domain\Messaging;

/**
 * What one round of monthly statements came to: how many went out, and who was skipped for
 * having no e-mail on the card.
 */
readonly class StatementRun
{
    /**
     * @param  list<string>  $skipped
     */
    public function __construct(
        public int $sent,
        public array $skipped = [],
    ) {}
}
