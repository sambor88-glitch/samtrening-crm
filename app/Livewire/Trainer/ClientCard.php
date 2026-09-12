<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Balance;
use App\Domain\Clients\Actions\SetClientRate;
use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The client card — docs/SPEC-EKRANY.md ekran 6. Training history (SC-24), files (SC-33) and the
 * RODO action bar (SC-45, SC-46) hang off this screen later.
 */
class ClientCard extends Component
{
    public Client $client;

    /** The rate in złoty, as typed into the bar. */
    public string $rate = '';

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);

        $this->client = $client;
        $this->rate = Money::toInput($client->rate);
    }

    /**
     * The dialog saved this card — show what it wrote.
     */
    #[On('client-saved')]
    public function refresh(): void
    {
        $this->client->refresh();
        $this->rate = Money::toInput($this->client->rate);
    }

    /**
     * Changing the rate never touches sessions already logged: those keep the amount they were
     * logged with, which is the whole point of being able to override it.
     */
    public function saveRate(SetClientRate $setRate): void
    {
        $this->authorize('update', $this->client);

        $this->validate(
            ['rate' => ['required', 'numeric', 'min:0', 'max:100000']],
            ['rate.required' => 'Podaj stawkę.', 'rate.numeric' => 'Stawka to kwota w złotych.'],
        );

        $setRate->handle(auth()->user(), $this->client, Money::fromInput($this->rate));

        $this->client->refresh();
        $this->rate = Money::toInput($this->client->rate);

        $this->dispatch(
            'toast',
            message: 'Stawka '.$this->client->name.' ustawiona na '.Money::format($this->client->rate).' za sesję.',
        );
    }

    /**
     * The button belongs on the card; sending the request is SC-31.
     */
    public function requestBlik(): void
    {
        $this->dispatch('toast', message: 'Prośba o BLIK poleci ze zgłoszenia SC-31 — na razie przycisk tylko czeka.');
    }

    public function render(Balance $balance): View
    {
        return view('livewire.trainer.client-card', [
            'balance' => $balance->forClient($this->client),
        ]);
    }
}
