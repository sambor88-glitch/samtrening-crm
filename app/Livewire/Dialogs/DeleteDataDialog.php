<?php

namespace App\Livewire\Dialogs;

use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Privacy\Actions\AnonymizeClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Żądanie usunięcia danych" — docs/SPEC-EKRANY.md §Usunięcie danych (RODO). The dialog exists
 * because this cannot be undone: it says what goes, what stays and why, and makes the person
 * type nothing they could mistype — they press the button or they close it.
 */
class DeleteDataDialog extends Component
{
    public bool $open = false;

    public ?int $clientId = null;

    #[On('delete-client-data')]
    public function openFor(int $client): void
    {
        $card = Client::findOrFail($client);

        $this->authorize('update', $card);

        $this->clientId = $card->getKey();
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset();
    }

    public function confirm(AnonymizeClient $anonymize): void
    {
        $card = Client::findOrFail($this->clientId);

        $this->authorize('update', $card);

        $name = $card->name;

        $anonymize->handle(auth()->user(), $card);

        $this->reset();

        $this->dispatch('client-data-deleted');
        $this->dispatch('toast', message: 'Dane '.$name.' usunięte. Została historia sesji bez notatek.');
    }

    public function render(Balance $balance): View
    {
        $client = $this->clientId ? Client::find($this->clientId) : null;

        return view('livewire.dialogs.delete-data-dialog', [
            'client' => $client,
            'owed' => $client ? $balance->forClient($client) : 0,
        ]);
    }
}
