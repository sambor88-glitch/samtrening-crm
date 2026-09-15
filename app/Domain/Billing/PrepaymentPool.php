<?php

namespace App\Domain\Billing;

use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Decides which sessions the money paid up front pays for — docs/START-TUTAJ.md §6.
 *
 * The pool goes to the sessions still owed, oldest first. A session it covers whole becomes
 * `prepaid`; the one it runs out on keeps the rest on the balance; everything after that is
 * beyond the pool and owed in full. Nothing is added up as it goes: every action that changes a
 * prepayment, a price or whether a session exists calls `allocate()` afterwards, and the whole
 * client is worked out again from scratch — so a missed step is corrected by the next one, not
 * carried forward.
 *
 * It only writes. What is owed and what is left is read by `Balance`, like any other balance.
 */
class PrepaymentPool
{
    /** Statuses the pool may pay for, or hand back to the balance when the money is gone. */
    private const array OPEN = [PaymentStatus::Balance, PaymentStatus::Requested, PaymentStatus::Prepaid];

    public function allocate(Client $client): void
    {
        DB::transaction(function () use ($client) {
            // Two saves on one card must not spend the same money twice. MySQL waits here; SQLite
            // has no row locks, but it lets only one writer in anyway.
            Client::query()->whereKey($client->getKey())->lockForUpdate()->first();

            $pool = (int) $client->prepayments()->sum('amount');

            if ($pool === 0 && ! $this->touchedByPool($client)) {
                return;
            }

            $sessions = $client->sessions()->orderBy('date')->orderBy('id')->get();

            // Settled partly from the pool and partly in cash: that part stays spent, because the
            // client paid the rest on that understanding. Only a pool that no longer holds the
            // money gives it up.
            foreach ($sessions->where('payment_status', PaymentStatus::Paid) as $session) {
                $part = min($session->prepaid_amount, $session->price, $pool);
                $pool -= $part;

                $this->write($session, PaymentStatus::Paid, $part);
            }

            foreach ($sessions->whereIn('payment_status', self::OPEN) as $session) {
                $part = min($session->price, $pool);
                $pool -= $part;

                $this->write($session, $this->status($session, $part), $part);
            }

            // A free cancellation costs nothing, so it takes nothing — even after a price edit.
            foreach ($sessions->where('payment_status', PaymentStatus::Waived) as $session) {
                $this->write($session, PaymentStatus::Waived, 0);
            }
        });
    }

    /**
     * No prepayment now, but maybe one before: then sessions still carry its marks and have to
     * go back to the balance. A client who never paid up front costs one query here.
     */
    private function touchedByPool(Client $client): bool
    {
        return $client->sessions()
            ->where(fn (Builder $query) => $query
                ->where('prepaid_amount', '>', 0)
                ->orWhere('payment_status', PaymentStatus::Prepaid))
            ->exists();
    }

    /**
     * Covered whole → paid from the pool. No longer covered → back on the balance. A payment
     * request stays a payment request while any of it is still owed.
     */
    private function status(TrainingSession $session, int $part): PaymentStatus
    {
        return match (true) {
            $part > 0 && $part === $session->price => PaymentStatus::Prepaid,
            $session->payment_status === PaymentStatus::Prepaid => PaymentStatus::Balance,
            default => $session->payment_status,
        };
    }

    private function write(TrainingSession $session, PaymentStatus $status, int $part): void
    {
        if ($session->payment_status === $status && $session->prepaid_amount === $part) {
            return;
        }

        // Not mass assignable on purpose: only the pool decides these two.
        $session->forceFill(['payment_status' => $status, 'prepaid_amount' => $part])->save();
    }
}
