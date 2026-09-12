<?php

namespace App\Domain\Billing;

/**
 * What a range came to. `revenue` and `completedSessions` differ on purpose: a charged
 * cancellation is money without a training.
 */
readonly class EarningsSummary
{
    public function __construct(
        public int $revenue,
        public int $completedSessions,
        public int $paid = 0,
        public int $owed = 0,
        public int $missedSessions = 0,
        public int $missedRevenue = 0,
    ) {}
}
