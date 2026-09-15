<?php

namespace App\Domain\Messaging;

use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\PolishMonth;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fills `{imie}`, `{blik}` and the rest — docs/START-TUTAJ.md §9. Placeholders stay in Polish
 * because trainers edit these texts themselves. `{blik}` is the number of the trainer who runs
 * this client, never the studio's.
 */
class TemplateRenderer
{
    public function __construct(private readonly Balance $balance) {}

    /**
     * @param  array<string, string>  $values
     */
    public function render(string $template, array $values): string
    {
        $replacements = [];

        foreach ($values as $placeholder => $value) {
            $replacements['{'.$placeholder.'}'] = $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * Everything a message about this client can need, taken from real data.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public function contextFor(Client $client, ?DateRange $month = null, array $extra = []): array
    {
        $month ??= DateRange::currentMonth();
        $trainer = $client->trainer;
        $inMonth = $client->sessions()
            ->whereBetween('date', [$month->firstDay(), $month->lastDay()])
            ->orderBy('date')
            ->get();
        $last = $client->sessions()->orderByDesc('date')->orderByDesc('id')->first();

        return [
            'imie' => Str::before($client->name, ' '),
            'trener' => Str::before($trainer->name, ' '),
            'trenerPelny' => $trainer->name,
            'blik' => $trainer->blik_number ?: '—',
            'saldo' => Money::format($this->balance->forClient($client)),
            'data' => $last?->date->format('d.m') ?? '—',
            // What the client pays for it: a prepayment's share is not asked for twice.
            'kwota' => Money::format($last?->beyondPrepayment() ?? $client->rate),
            'miesiac' => PolishMonth::genitive($month->start()),
            'miesiacB' => PolishMonth::accusative($month->start()),
            'miesiacW' => PolishMonth::locative($month->start()),
            'lista' => $this->sessionList($inMonth),
            'sumaListy' => Money::format($this->owed($inMonth)),
            ...$extra,
        ];
    }

    /**
     * One line per session, with cancellations named — a statement that hides them invites the
     * question "what is this charge".
     *
     * @param  Collection<int, TrainingSession>  $sessions
     */
    private function sessionList(Collection $sessions): string
    {
        if ($sessions->isEmpty()) {
            return '(brak sesji w tym miesiącu)';
        }

        return $sessions
            ->map(fn (TrainingSession $session) => '· '.$session->date->format('d.m').' — '
                .$session->service.' — '.Money::format($session->price).$this->note($session))
            ->implode("\n");
    }

    private function note(TrainingSession $session): string
    {
        $kind = match (true) {
            $session->kind === SessionKind::NoShow => ' · nieobecność',
            $session->kind !== SessionKind::Cancelled => '',
            $session->payment_status === PaymentStatus::Waived => ' · odwołanie bez naliczenia',
            default => ' · odwołanie po terminie',
        };

        // Paid up front is paid: the line says so, and "do zapłaty" leaves it out.
        $prepaid = match (true) {
            $session->isPrepaid() => ' · z przedpłaty',
            $session->prepaid_amount > 0 => ' · '.Money::format($session->prepaid_amount).' z przedpłaty',
            default => '',
        };

        // So is paid on the spot, the rest of a session the prepayment ran out on included.
        $paid = $session->payment_status === PaymentStatus::Paid ? ' · zapłacone' : '';

        return $kind.$prepaid.$paid;
    }

    /**
     * What the statement asks for: the sessions still owed, each less a prepayment's share —
     * Billing\Balance's rule, narrowed to the month. A session paid on the spot is listed, not counted.
     *
     * @param  Collection<int, TrainingSession>  $sessions
     */
    private function owed(Collection $sessions): int
    {
        return (int) $sessions
            ->filter(fn (TrainingSession $session) => $session->isPayable())
            ->sum(fn (TrainingSession $session) => $session->beyondPrepayment());
    }
}
