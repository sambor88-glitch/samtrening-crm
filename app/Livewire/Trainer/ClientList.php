<?php

namespace App\Livewire\Trainer;

use App\Domain\Clients\Enums\RosterFilter;
use App\Domain\Clients\Queries\ClientRoster;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class ClientList extends Component
{
    #[Url(as: 'szukaj', except: '')]
    public string $search = '';

    /** The default has to be written out — an attribute argument cannot call the enum. */
    #[Url(as: 'filtr', except: 'aktywni')]
    public string $filter = 'aktywni';

    public function render(ClientRoster $roster): View
    {
        $filter = RosterFilter::tryFrom($this->filter) ?? RosterFilter::Active;

        $clients = $roster->forTrainer(auth()->user(), $filter, $this->search);

        return view('livewire.trainer.client-list', [
            'rows' => $clients->rows,
            'total' => $clients->total,
            'filters' => RosterFilter::options(),
            'emptyMessage' => $this->emptyMessage($filter, $clients->total),
        ]);
    }

    /**
     * Three different silences, three different things to say — an empty archive is not the
     * same as an empty roster, and neither is a filter that matched nobody.
     */
    private function emptyMessage(RosterFilter $filter, int $total): string
    {
        return match (true) {
            $filter === RosterFilter::Archived => 'Archiwum jest puste — nikogo jeszcze nie zarchiwizowałeś.',
            $total === 0 => 'Kartoteka jest pusta. Dodaj pierwszego klienta — zajmie to dwadzieścia sekund.',
            default => 'Nikt nie pasuje do tego filtra.',
        };
    }
}
