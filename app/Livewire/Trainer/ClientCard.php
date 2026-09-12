<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Actions\RequestBlikPayment;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Actions\ArchiveClient;
use App\Domain\Clients\Actions\AttachClientFile;
use App\Domain\Clients\Actions\SendClientFile;
use App\Domain\Clients\Actions\SetClientRate;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Models\ClientFile;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Privacy\Actions\ExportClientData;
use App\Domain\Training\Actions\DeleteSession;
use App\Domain\Training\Actions\RestoreSession;
use App\Domain\Training\Actions\UpdateSessionPrice;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client card — docs/SPEC-EKRANY.md ekran 6. Files (SC-33) and the RODO action bar
 * (SC-45, SC-46) hang off this screen later.
 */
class ClientCard extends Component
{
    use WithFileUploads;

    public Client $client;

    /** The rate in złoty, as typed into the bar. */
    public string $rate = '';

    /** Session id => amount in złoty, edited straight in the history. */
    public array $prices = [];

    /** The plan being uploaded right now. */
    public $upload = null;

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

    /**
     * Asks the client to pay what is on the balance. The message goes through the queue, and if
     * it cannot go at all the statuses stay put — "Poproszono" must mean somebody was asked.
     */
    public function requestBlik(): void
    {
        $this->authorize('update', $this->client);

        $owed = app(Balance::class)->forClient($this->client);

        try {
            app(RequestBlikPayment::class)->handle(auth()->user(), $this->client);
        } catch (MessageNotPossible $blocked) {
            $this->dispatch('toast', message: $blocked->getMessage(), variant: 'error');

            return;
        }

        $this->dispatch(
            'toast',
            message: 'Prośba o BLIK do '.$this->client->name.' — '.Money::format($owed).'. SMS poszedł do kolejki.',
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

    /**
     * Plans go to the private disk; the client only ever gets a link that expires.
     */
    public function uploadFile(): void
    {
        $this->authorize('update', $this->client);

        $this->validate([
            'upload' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp,heic'],
        ], [
            'upload.required' => 'Wybierz plik.',
            'upload.max' => 'Plik może mieć najwyżej 8 MB.',
            'upload.mimes' => 'Przyjmujemy PDF-y i zdjęcia.',
        ]);

        $file = app(AttachClientFile::class)->handle(auth()->user(), $this->client, $this->upload);

        $this->reset('upload');

        $this->dispatch('toast', message: 'Plik '.$file->name.' jest na karcie.');
    }

    public function sendFile(int $file): void
    {
        $this->authorize('update', $this->client);

        $record = ClientFile::query()
            ->where('client_id', $this->client->getKey())
            ->findOrFail($file);

        try {
            app(SendClientFile::class)->handle(auth()->user(), $record);
        } catch (MessageNotPossible $blocked) {
            $this->dispatch('toast', message: $blocked->getMessage(), variant: 'error');

            return;
        }

        $this->dispatch(
            'toast',
            message: 'Link do „'.$record->name.'" poszedł SMS-em. Wygasa za '
                .SendClientFile::LINK_DAYS.' dni.',
        );
    }

    /**
     * Everything the studio holds about this person, in one readable file — art. 15 RODO.
     */
    public function exportData(ExportClientData $export): StreamedResponse
    {
        $this->authorize('view', $this->client);

        $file = $export->handle(auth()->user(), $this->client);

        $this->dispatch('toast', message: 'Dane '.$this->client->name.' pobrane. Przekaż plik tylko tej osobie.');

        // Straight to the output stream, like the CSV export: no echo, no temporary file.
        return response()->streamDownload(
            function () use ($file) {
                $output = fopen('php://output', 'w');
                fwrite($output, $file->contents);
                fclose($output);
            },
            $file->name,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function toggleArchive(ArchiveClient $archive): void
    {
        $this->authorize('update', $this->client);

        try {
            $archive->handle(auth()->user(), $this->client, ! $this->client->archived);
        } catch (RuntimeException $blocked) {
            $this->dispatch('toast', message: $blocked->getMessage(), variant: 'error');

            return;
        }

        $this->client->refresh();

        $this->dispatch('toast', message: $this->client->archived
            ? $this->client->name.' w archiwum. Znika z list i statystyk, sesje zostają w zarobkach.'
            : $this->client->name.' z powrotem na liście.');
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
            'files' => $this->client->files()->latest()->get(),
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
