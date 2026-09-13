<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Actions\RequestBlikPayment;
use App\Domain\Billing\Balance;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendMonthlyStatements;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Messaging\StatementRun;
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

    public function sendStatements(SendMonthlyStatements $statements): void
    {
        $run = $statements->handle(auth()->user(), DateRange::currentMonth());

        $this->dispatch('toast', message: $this->statementSummary($run));
    }

    private function statementSummary(StatementRun $run): string
    {
        $message = 'Podsumowania w drodze: '.Plural::of($run->sent, 'klient', 'klienci', 'klientów').'.';

        if ($run->skipped !== []) {
            $message .= ' Bez e-maila na karcie: '.implode(', ', $run->skipped).'.';
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
            // Liczba na przycisku = liczba adresatów, a nie liczba wierszy zaległości: od SC-57
            // to dwie różne rzeczy, gdy ktoś wisi wyłącznie ze starszych miesięcy.
            'statements' => $statements = $outstanding->owingIn($trainer, $month)->count(),
            'statementsLabel' => Plural::of($statements, 'podsumowanie', 'podsumowania', 'podsumowań'),
        ]);
    }
}
