<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Clients\Models\Client;
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

    /**
     * The button belongs on this screen; the SMS itself is SC-31.
     */
    public function requestBlik(int $client): void
    {
        $card = Client::findOrFail($client);

        $this->authorize('view', $card);

        $this->dispatch('toast', message: 'SMS z prośbą o BLIK poleci ze zgłoszenia SC-31 — na razie przycisk czeka.');
    }

    /**
     * The monthly statement mail is SC-32.
     */
    public function sendStatements(): void
    {
        $this->dispatch('toast', message: 'Zbiorcze podsumowania wyśle zgłoszenie SC-32 — na razie przycisk czeka.');
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
