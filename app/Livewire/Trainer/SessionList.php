<?php

namespace App\Livewire\Trainer;

use App\Domain\Training\Queries\SessionHistory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Co się odbyło" — docs/SPEC-EKRANY.md ekran 7. Sessions pile up fast, so the screen shows a
 * page at a time and loads older ones on demand.
 */
class SessionList extends Component
{
    private const int PAGE = 25;

    public int $perPage = self::PAGE;

    public function more(): void
    {
        $this->perPage += self::PAGE;
    }

    #[On('session-logged')]
    public function refresh(): void {}

    public function render(SessionHistory $history): View
    {
        return view('livewire.trainer.session-list', [
            'page' => $history->forTrainer(auth()->user(), $this->perPage),
        ]);
    }
}
