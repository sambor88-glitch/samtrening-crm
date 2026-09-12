<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Earnings;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendReminder;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Training\Queries\SessionHistory;
use App\Domain\Training\Queries\WeekGrid;
use App\Support\DateRange;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Cześć, {imię}." — docs/SPEC-EKRANY.md ekran 4. The first screen after logging in, so it
 * answers the two questions a trainer actually has: what is left to log, and who still owes.
 */
class Dashboard extends Component
{
    private const int RECENT = 6;

    #[On('session-logged')]
    public function refresh(): void {}

    public function remind(int $client): void
    {
        $card = Client::findOrFail($client);

        $this->authorize('view', $card);

        try {
            app(SendReminder::class)->handle(auth()->user(), $card);
        } catch (MessageNotPossible $blocked) {
            // Nothing about the money changed — only the text did not go out.
            $this->dispatch('toast', message: $blocked->getMessage(), variant: 'error');

            return;
        }

        $this->dispatch('toast', message: 'Monit do '.$card->name.' poszedł do kolejki.');
    }

    public function render(
        Outstanding $outstanding,
        Earnings $earnings,
        SessionHistory $history,
        WeekGrid $week,
    ): View {
        $trainer = auth()->user();
        $month = DateRange::currentMonth();
        $owed = $outstanding->forTrainer($trainer);

        return view('livewire.trainer.dashboard', [
            'firstName' => str($trainer->name)->before(' ')->toString(),
            'month' => $month,
            'summary' => $earnings->forTrainer($trainer, $month),
            'owed' => $owed,
            'owedTotal' => (int) $owed->sum(fn (OutstandingRow $row) => $row->amount),
            'recent' => $history->forTrainer($trainer, self::RECENT)->rows,
            'week' => $week->forTrainer($trainer),
            'clients' => Client::query()->forTrainer($trainer)->where('archived', false)->count(),
            // "Na następny raz" belongs to the trainer's own head: it never reaches the client.
            'plans' => Client::query()
                ->forTrainer($trainer)
                ->where('archived', false)
                ->whereNotNull('next_session_plan')
                ->where('next_session_plan', '!=', '')
                ->orderBy('name')
                ->get(['id', 'name', 'next_session_plan']),
        ]);
    }
}
