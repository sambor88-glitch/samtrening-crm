<?php

namespace App\Domain\Billing;

/**
 * Where a client's prepayments stand, in grosze: paid in, spent on sessions, still in the pool.
 */
readonly class PrepaymentSummary
{
    public int $left;

    public function __construct(
        public int $paidIn,
        public int $used,
    ) {
        $this->left = max(0, $paidIn - $used);
    }

    /**
     * The client never paid up front — the card and the dialog say nothing about a pool.
     */
    public function isEmpty(): bool
    {
        return $this->paidIn === 0;
    }

    /**
     * Whole sessions at this rate that what is left still pays for.
     */
    public function sessionsLeft(int $rate): int
    {
        return $rate > 0 ? intdiv($this->left, $rate) : 0;
    }

    /**
     * What a session at this price would put on the balance, once the pool has paid its share.
     */
    public function shortfall(int $price): int
    {
        return max(0, $price - $this->left);
    }
}
