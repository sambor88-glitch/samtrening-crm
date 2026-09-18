<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Logging a session is the only moment money becomes due — docs/START-TUTAJ.md §6. Everything
 * else in the product only reads what this action writes.
 */
class LogSession
{
    /** A second click on a bad connection lands within seconds of the first (§11). */
    private const int DOUBLE_CLICK_SECONDS = 5;

    public function __construct(
        private readonly ActivityLogger $log,
        private readonly PrepaymentPool $pool,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  date, service, price (grosze), kind, payment_status,
     *                                            notes and optionally next_session_plan
     */
    public function handle(User $actor, Client $client, array $attributes): TrainingSession
    {
        $kind = $attributes['kind'] instanceof SessionKind
            ? $attributes['kind']
            : SessionKind::from($attributes['kind']);

        $status = $attributes['payment_status'] instanceof PaymentStatus
            ? $attributes['payment_status']
            : PaymentStatus::from($attributes['payment_status']);

        $price = (int) $attributes['price'];

        if ($existing = $this->justLogged($client, $attributes['date'], $price)) {
            return $existing;
        }

        $session = $client->sessions()->create([
            'date' => $attributes['date'],
            'service' => $attributes['service'],
            'price' => $price,
            'kind' => $kind,
            'payment_status' => $status,
            'notes' => $attributes['notes'] ?? null,
        ]);

        // Paid on the spot: the money arrived now, and the monthly cash-flow figure in the agent
        // API counts it from this column rather than from the session date.
        if ($status === PaymentStatus::Paid) {
            $session->forceFill(['paid_at' => CarbonImmutable::now(config('app.timezone'))])->save();
        }

        // "Na następny raz" belongs to the client, not to the session: it is what to do next time.
        if (array_key_exists('next_session_plan', $attributes)) {
            $client->update(['next_session_plan' => $attributes['next_session_plan']]);
        }

        // Money paid up front may pay for this session — or, when it is dated before sessions the
        // pool already covers, for this one instead of the latest. The pool works that out.
        $this->pool->allocate($client);
        $session->refresh();

        $this->log->record(
            $actor,
            $this->action($kind),
            $client->name.' · '.Money::format($price).' · '.$this->settlement($session),
        );

        return $session;
    }

    /**
     * The same client, the same day, the same amount, seconds ago — that is the second click,
     * not a second session (docs/START-TUTAJ.md §11).
     */
    private function justLogged(Client $client, string $date, int $price): ?TrainingSession
    {
        return $client->sessions()
            ->where('date', $date)
            ->where('price', $price)
            ->where('created_at', '>=', now()->subSeconds(self::DOUBLE_CLICK_SECONDS))
            ->latest('id')
            ->first();
    }

    private function action(SessionKind $kind): string
    {
        return match ($kind) {
            SessionKind::Completed => 'Wbił sesję',
            SessionKind::Cancelled => 'Zapisał odwołanie',
            SessionKind::NoShow => 'Zapisał nieobecność',
        };
    }

    /**
     * How the session ended up settled once the pool had its say — a session paid out of a
     * prepayment is not "na saldo" in the log.
     */
    private function settlement(TrainingSession $session): string
    {
        $settlement = match ($session->payment_status) {
            PaymentStatus::Waived => 'nie naliczono',
            PaymentStatus::Paid => 'zapłacone',
            PaymentStatus::Prepaid => 'z przedpłaty',
            PaymentStatus::Requested => 'prośba o BLIK',
            PaymentStatus::Balance => 'na saldo',
        };

        return $session->isPayable() && $session->prepaid_amount > 0
            ? $settlement.' · '.Money::format($session->prepaid_amount).' z przedpłaty'
            : $settlement;
    }
}
