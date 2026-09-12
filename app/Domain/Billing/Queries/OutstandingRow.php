<?php

namespace App\Domain\Billing\Queries;

use App\Domain\Clients\Models\Client;
use Carbon\CarbonImmutable;

/**
 * One client who still owes something: how many sessions, how much, since when — and when they
 * last trained, which is what the trainer's dashboard shows (ekran 4).
 */
readonly class OutstandingRow
{
    public function __construct(
        public Client $client,
        public int $sessions,
        public int $amount,
        public CarbonImmutable $oldestOn,
        public CarbonImmutable $latestOn,
        public int $days,
        public bool $requested,
    ) {}

    /**
     * Past the studio's patience — `settings.reminder_threshold_days`.
     */
    public function isOverdue(int $thresholdDays): bool
    {
        return $this->days > $thresholdDays;
    }
}
