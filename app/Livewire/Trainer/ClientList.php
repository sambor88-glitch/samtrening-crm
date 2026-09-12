<?php

namespace App\Livewire\Trainer;

use App\Domain\Clients\Enums\RosterFilter;
use App\Domain\Clients\Queries\ClientRoster;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class ClientList extends Component
{
    #[Url(as: 'szukaj', except: '')]
    public string $search = '';

    /** The default has to be written out — an attribute argument cannot call the enum. */
    #[Url(as: 'filtr', except: 'aktywni')]
    public string $filter = 'aktywni';

    /**
     * The dialog saved a card — the list below it has to catch up.
     */
    #[On('client-saved')]
    #[On('session-logged')]
    public function refresh(): void {}

    public function render(ClientRoster $roster): View
    {
        $filter = RosterFilter::tryFrom($this->filter) ?? RosterFilter::Active;

        $clients = $roster->forTrainer(auth()->user(), $filter, $this->search);

        return view('livewire.trainer.client-list', [
            'rows' => $clients->rows,
            'total' => $clients->total,
            'filters' => RosterFilter::options(),
            'empty' => $this->emptyState($filter, $clients->total),
        ]);
    }

    /**
     * Three different silences, three different things to say — an empty archive is not the same
     * as an empty roster, and neither is a filter that matched nobody. `add` says whether the
     * "dodaj klienta" button belongs there: it does not fill an archive.
     *
     * @return array{title: string, message: string, add: bool}
     */
    private function emptyState(RosterFilter $filter, int $total): array
    {
        return match (true) {
            $filter === RosterFilter::Archived => [
                'title' => 'Archiwum jest puste',
                'message' => 'Nikogo jeszcze nie zarchiwizowałeś. Karta trafia tu dopiero po rozliczeniu salda.',
                'add' => false,
            ],
            $total === 0 => [
                'title' => 'Kartoteka jest pusta',
                'message' => 'Dodaj pierwszego klienta — zajmie to dwadzieścia sekund.',
                'add' => true,
            ],
            default => [
                'title' => 'Nikt nie pasuje',
                'message' => 'Żaden klient nie pasuje do tego filtra. Zmień go albo wyczyść wyszukiwanie.',
                'add' => false,
            ],
        };
    }
}
