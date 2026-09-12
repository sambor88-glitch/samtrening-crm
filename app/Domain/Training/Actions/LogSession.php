<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;

/**
 * Logging a session is the only moment money becomes due — docs/START-TUTAJ.md §6. Everything
 * else in the product only reads what this action writes.
 */
class LogSession
{
    /** A second click on a bad connection lands within seconds of the first (§11). */
    private const int DOUBLE_CLICK_SECONDS = 5;

    public function __construct(private readonly ActivityLogger $log) {}

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

        // "Na następny raz" belongs to the client, not to the session: it is what to do next time.
        if (array_key_exists('next_session_plan', $attributes)) {
            $client->update(['next_session_plan' => $attributes['next_session_plan']]);
        }

        $this->log->record(
            $actor,
            $this->action($kind),
            $client->name.' · '.Money::format($price).' · '.$this->settlement($status),
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

    private function settlement(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Waived => 'nie naliczono',
            PaymentStatus::Paid => 'zapłacone',
            PaymentStatus::Requested => 'prośba o BLIK',
            PaymentStatus::Balance => 'na saldo',
        };
    }
}
