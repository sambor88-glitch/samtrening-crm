<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Balance;
use App\Domain\Clients\Actions\SetClientRate;
use App\Domain\Clients\Models\Client;
use App\Domain\Training\Actions\DeleteSession;
use App\Domain\Training\Actions\RestoreSession;
use App\Domain\Training\Actions\UpdateSessionPrice;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The client card — docs/SPEC-EKRANY.md ekran 6. Files (SC-33) and the RODO action bar
 * (SC-45, SC-46) hang off this screen later.
 */
class ClientCard extends Component
{
    public Client $client;

    /** The rate in złoty, as typed into the bar. */
    public string $rate = '';

    /** Session id => amount in złoty, edited straight in the history. */
    public array $prices = [];

    public function mount(Client $client): void
    {
        $this->authorize('view', $client);

        $this->client = $client;
        $this->rate = Money::toInput($client->rate);
    }

    /**
     * The dialogs saved something on this card — show what they wrote.
     */
    #[On('client-saved')]
    #[On('session-logged')]
    public function refresh(): void
    {
        $this->client->refresh();
        $this->rate = Money::toInput($this->client->rate);
    }

    /**
     * Changing the rate never touches sessions already logged: those keep the amount they were
     * logged with, which is the whole point of being able to override it.
     */
    public function saveRate(): void
    {
        $this->authorize('update', $this->client);

        $this->validate(
            ['rate' => ['required', 'numeric', 'min:0', 'max:100000']],
            ['rate.required' => 'Podaj stawkę.', 'rate.numeric' => 'Stawka to kwota w złotych.'],
        );

        app(SetClientRate::class)->handle(auth()->user(), $this->client, Money::fromInput($this->rate));

        $this->client->refresh();
        $this->rate = Money::toInput($this->client->rate);

        $this->dispatch(
            'toast',
            message: 'Stawka '.$this->client->name.' ustawiona na '.Money::format($this->client->rate).' za sesję.',
        );
    }

    public function saveSessionPrice(int $session): void
    {
        $model = $this->session($session);

        $this->authorize('update', $model);

        $this->validate(
            ['prices.'.$session => ['required', 'numeric', 'min:0', 'max:100000']],
            [
                'prices.'.$session.'.required' => 'Podaj kwotę.',
                'prices.'.$session.'.numeric' => 'Kwota to liczba w złotych.',
            ],
        );

        app(UpdateSessionPrice::class)->handle(auth()->user(), $model, Money::fromInput($this->prices[$session]));

        $this->prices[$session] = Money::toInput($model->fresh()->price);

        $this->dispatch('toast', message: 'Kwota sesji zapisana: '.Money::format($model->fresh()->price).'.');
    }

    /**
     * No confirmation dialog: the toast holds "Cofnij" for eight seconds, and the row is only
     * soft deleted, so nothing is actually gone.
     */
    public function deleteSession(int $session): void
    {
        $model = $this->session($session);

        $this->authorize('delete', $model);

        app(DeleteSession::class)->handle(auth()->user(), $model);

        $this->dispatch(
            'toast',
            message: 'Sesja '.$this->describe($model).' usunięta z karty '.$this->client->name.'.',
            action: ['label' => 'Cofnij', 'event' => 'session-restore', 'params' => ['session' => $session]],
        );
    }

    #[On('session-restore')]
    public function restoreSession(int $session): void
    {
        $model = $this->session($session);

        $this->authorize('restore', $model);

        app(RestoreSession::class)->handle(auth()->user(), $model);

        $this->dispatch('toast', message: 'Przywrócone — sesja '.$this->describe($model).' wróciła na kartę.');
    }

    public function render(Balance $balance): View
    {
        $sessions = $this->sessions();

        foreach ($sessions as $session) {
            $this->prices[$session->getKey()] ??= Money::toInput($session->price);
        }

        return view('livewire.trainer.client-card', [
            'balance' => $balance->forClient($this->client),
            'sessions' => $sessions,
        ]);
    }

    /**
     * @return Collection<int, TrainingSession>
     */
    private function sessions(): Collection
    {
        return $this->client->sessions()->orderByDesc('date')->orderByDesc('id')->get();
    }

    private function session(int $id): TrainingSession
    {
        return TrainingSession::withTrashed()
            ->where('client_id', $this->client->getKey())
            ->findOrFail($id);
    }

    private function describe(TrainingSession $session): string
    {
        return $session->date->format('d.m.Y').' · '.Money::format($session->price);
    }
}
