<?php

namespace App\Domain\Billing;

/**
 * The two numbers the earnings screens show side by side: money charged in the range (grosze)
 * and sessions actually held. They differ on purpose — a charged cancellation is money without
 * a training.
 */
readonly class EarningsSummary
{
    public function __construct(
        public int $revenue,
        public int $completedSessions,
    ) {}
}
