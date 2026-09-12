<?php

namespace App\Livewire\Admin;

use App\Domain\Billing\Actions\NudgeTrainer;
use App\Domain\Billing\Queries\Outstanding as OutstandingQuery;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "Co wisi nieopłacone." — docs/SPEC-EKRANY.md ekran 15. The owner watches the pile; the nudge
 * goes to the trainer, because the client's conversation belongs to them.
 */
class Outstanding extends Component
{
    public function nudge(int $client): void
    {
        $this->authorize('viewAny', User::class);

        $card = Client::findOrFail($client);

        app(NudgeTrainer::class)->handle(auth()->user(), $card);

        $this->dispatch(
            'toast',
            message: $card->trainer->name.' zobaczy przypomnienie o '.$card->name.' przy najbliższym wejściu.',
        );
    }

    public function render(OutstandingQuery $outstanding): View
    {
        $this->authorize('viewAny', User::class);

        $rows = $outstanding->forStudio();

        return view('livewire.admin.outstanding', [
            'rows' => $rows,
            'total' => (int) $rows->sum(fn (OutstandingRow $row) => $row->amount),
            'threshold' => (int) (Setting::query()->value('reminder_threshold_days') ?? 14),
        ]);
    }
}
