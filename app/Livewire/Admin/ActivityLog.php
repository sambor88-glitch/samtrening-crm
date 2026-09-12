<?php

namespace App\Livewire\Admin;

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Team\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "Kto co zmienił." — docs/SPEC-EKRANY.md ekran 16. Append-only: there is no edit and no delete
 * here, not even for the owner, because a log somebody can tidy up answers nothing.
 */
class ActivityLog extends Component
{
    private const int PAGE = 50;

    public int $perPage = self::PAGE;

    public function more(): void
    {
        $this->perPage += self::PAGE;
    }

    public function render(): View
    {
        $this->authorize('viewAny', User::class);

        return view('livewire.admin.activity-log', [
            'entries' => ActivityEntry::query()
                ->orderByDesc('happened_at')
                ->orderByDesc('id')
                ->limit($this->perPage)
                ->get(),
            'total' => ActivityEntry::query()->count(),
        ]);
    }
}
