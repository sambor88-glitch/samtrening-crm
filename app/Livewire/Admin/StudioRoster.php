<?php

namespace App\Livewire\Admin;

use App\Domain\Clients\Queries\ClientRoster;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Wszyscy klienci." — docs/SPEC-EKRANY.md ekran 14. The owner sees the whole cabinet, archive
 * included, and walks into the same client card everyone else uses.
 */
class StudioRoster extends Component
{
    #[Url(as: 'szukaj', except: '')]
    public string $search = '';

    /** Trainer id, or an empty string for the whole studio. */
    #[Url(as: 'trener', except: '')]
    public string $trainer = '';

    public function render(ClientRoster $roster): View
    {
        $this->authorize('viewAny', User::class);

        $clients = $roster->forStudio($this->trainer === '' ? null : (int) $this->trainer, $this->search);

        // Built from the accounts that exist, so a newly activated trainer shows up by themselves.
        $trainers = User::query()
            ->whereIn('status', [UserStatus::Active, UserStatus::Blocked])
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return view('livewire.admin.studio-roster', [
            'rows' => $clients->rows,
            'total' => $clients->total,
            'trainers' => ['' => 'Wszyscy'] + $trainers,
        ]);
    }
}
