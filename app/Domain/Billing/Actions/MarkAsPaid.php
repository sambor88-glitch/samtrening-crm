<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Money arrives in cash, by transfer or through BLIK, and none of that reaches this app — so a
 * person marks it, never a robot. Everything the client still owed becomes paid at once, which
 * is how it works at the door: they hand over what is due, not a session at a time.
 *
 * A session a prepayment paid for in part is due only the rest, and keeps the part it took from
 * the pool — otherwise that money would turn up in the pool a second time.
 */
class MarkAsPaid
{
    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    /**
     * @param  User|null  $actor  null when a token acts instead of a person (SC-66)
     * @param  CarbonImmutable|null  $loggedBefore  settle only what was already on the card at
     *                                              this moment. The dashboard sends the instant
     *                                              its button was pressed: the agent posts on a
     *                                              cycle, so the same click can arrive twice, and
     *                                              a session logged between the click and the post
     *                                              must not be paid off by a press that predates
     *                                              it. Repeating a post then settles nothing,
     *                                              which is the idempotency this needs.
     * @param  string|null  $actorName  what to call a non-human actor in the log
     * @return int the amount marked as paid, in grosze
     */
    public function handle(
        ?User $actor,
        Client $client,
        ?CarbonImmutable $loggedBefore = null,
        ?string $actorName = null,
    ): int {
        $owed = $client->sessions()
            ->whereNotIn('payment_status', PaymentStatus::SETTLED)
            ->when($loggedBefore, fn ($query) => $query->where('created_at', '<=', $loggedBefore))
            ->get();

        if ($owed->isEmpty()) {
            return 0;
        }

        $amount = (int) $owed->sum(fn (TrainingSession $session) => $session->beyondPrepayment());

        // `paid_at` is when the money arrived, which is not the day of the session: a client
        // settling three weeks of training pays once, today. The agent API reports a month's
        // takings from this column (docs/AGENT-API.md §5).
        $client->sessions()
            ->whereKey($owed->modelKeys())
            ->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => CarbonImmutable::now(config('app.timezone')),
            ]);

        $this->pool->allocate($client);

        $this->log->record(
            $actor,
            'Odznaczył płatność',
            $client->name.' · '.Money::format($amount),
            $actorName,
        );

        return $amount;
    }
}
