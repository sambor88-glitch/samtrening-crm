<?php

namespace App\Livewire\Trainer;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Actions\RequestBlikPayment;
use App\Domain\Billing\Balance;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendMonthlyStatement;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Settings\Models\Setting;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Plural;
use App\Support\PolishMonth;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Kto zapłacił. Kto jeszcze nie." — docs/SPEC-EKRANY.md ekran 8. Payments arrive in cash, by
 * transfer or through BLIK, so nothing here marks itself: a person does, and the log remembers.
 */
class Payments extends Component
{
    #[On('session-logged')]
    public function refresh(): void {}

    public function markPaid(int $client): void
    {
        $card = Client::findOrFail($client);

        $this->authorize('update', $card);

        $amount = app(MarkAsPaid::class)->handle(auth()->user(), $card);

        $this->dispatch(
            'toast',
            message: $card->name.' — '.Money::format($amount).' odznaczone jako zapłacone.',
        );
    }

    public function requestBlik(int $client): void
    {
        $card = Client::findOrFail($client);

        $this->authorize('view', $card);

        $owed = app(Balance::class)->forClient($card);

        try {
            app(RequestBlikPayment::class)->handle(auth()->user(), $card);
        } catch (MessageNotPossible $blocked) {
            // The session data is untouched; only the message did not happen.
            $this->dispatch('toast', message: $blocked->getMessage(), variant: 'error');

            return;
        }

        $this->dispatch(
            'toast',
            message: 'Prośba o BLIK do '.$card->name.' — '.Money::format($owed).'. SMS poszedł do kolejki.',
        );
    }

    /**
     * One statement per client who owes something. Nothing here runs on a schedule: the amount
     * comes from sessions typed in by hand, and a robot would send last month's total the moment
     * somebody forgot to log two sessions (§3).
     */
    public function sendStatements(Outstanding $outstanding, SendMonthlyStatement $statement): void
    {
        $trainer = auth()->user();
        $month = DateRange::currentMonth();
        $sent = 0;
        $skipped = [];

        foreach ($outstanding->forTrainer($trainer) as $row) {
            try {
                $statement->handle($trainer, $row->client, $month);
                $sent++;
            } catch (MessageNotPossible $blocked) {
                $skipped[] = $row->client->name;
            }
        }

        app(ActivityLogger::class)->record(
            $trainer,
            'Wysłał zbiorcze podsumowania',
            Plural::of($sent, 'klient', 'klienci', 'klientów').' · '.PolishMonth::withYear($month->start()),
        );

        $this->dispatch('toast', message: $this->statementSummary($sent, $skipped));
    }

    /**
     * @param  list<string>  $skipped
     */
    private function statementSummary(int $sent, array $skipped): string
    {
        $message = 'Podsumowania w drodze: '.Plural::of($sent, 'klient', 'klienci', 'klientów').'.';

        if ($skipped !== []) {
            $message .= ' Bez e-maila na karcie: '.implode(', ', $skipped).'.';
        }

        return $message;
    }

    public function render(Outstanding $outstanding): View
    {
        $trainer = auth()->user();
        $month = DateRange::currentMonth();
        $rows = $outstanding->forTrainer($trainer);

        return view('livewire.trainer.payments', [
            'rows' => $rows,
            'threshold' => (int) (Setting::query()->value('reminder_threshold_days') ?? 14),
            'total' => (int) $rows->sum(fn (OutstandingRow $row) => $row->amount),
            'paidThisMonth' => $outstanding->paidIn($trainer, $month),
            'requests' => $outstanding->pendingRequests($trainer),
            'inMonth' => PolishMonth::inMonth($month->start()),
            'monthName' => PolishMonth::withYear($month->start()),
            'statements' => Plural::of($rows->count(), 'podsumowanie', 'podsumowania', 'podsumowań'),
        ]);
    }
}
