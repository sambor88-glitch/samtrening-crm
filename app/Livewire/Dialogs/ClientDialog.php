<?php

namespace App\Livewire\Dialogs;

use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\UpdateClient;
use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One window, two modes — docs/SPEC-EKRANY.md §Dodaj / edytuj klienta. The writing itself is
 * the actions' job; this only collects the fields and says what happened.
 */
class ClientDialog extends Component
{
    /** The prototype opens a new card at 200 zł. Nobody has asked for a studio-wide default. */
    private const string DEFAULT_RATE = '200';

    public bool $open = false;

    public ?int $clientId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $rate = self::DEFAULT_RATE;

    public string $goal = '';

    public string $contraindications = '';

    public string $guardian = '';

    public string $trainerNotes = '';

    public bool $invoice = false;

    public string $companyName = '';

    public string $taxId = '';

    public bool $consent = false;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'contraindications' => ['nullable', 'string', 'max:2000'],
            'guardian' => ['nullable', 'string', 'max:255'],
            'trainerNotes' => ['nullable', 'string', 'max:2000'],
            'companyName' => [$this->invoice ? 'required' : 'nullable', 'string', 'max:255'],
            'taxId' => [$this->invoice ? 'required' : 'nullable', 'string', 'max:15'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Podaj imię i nazwisko.',
            'rate.required' => 'Podaj stawkę.',
            'rate.numeric' => 'Stawka to kwota w złotych.',
            'email.email' => 'To nie wygląda na adres e-mail.',
            'companyName.required' => 'Podaj nazwę firmy — bez niej faktura nie wyjdzie.',
            'taxId.required' => 'Podaj NIP.',
        ];
    }

    #[On('add-client')]
    public function openForNew(): void
    {
        $this->authorize('create', Client::class);

        $this->reset();
        $this->resetValidation();
        $this->open = true;
    }

    #[On('edit-client')]
    public function openForEdit(int $client): void
    {
        $card = Client::findOrFail($client);

        $this->authorize('update', $card);

        $this->resetValidation();

        $this->clientId = $card->getKey();
        $this->name = $card->name;
        $this->phone = (string) $card->phone;
        $this->email = (string) $card->email;
        $this->rate = Money::toInput($card->rate);
        $this->goal = (string) $card->goal;
        $this->contraindications = (string) $card->contraindications;
        $this->guardian = (string) $card->guardian;
        $this->trainerNotes = (string) $card->trainer_notes;
        $this->invoice = filled($card->company_name) || filled($card->tax_id);
        $this->companyName = (string) $card->company_name;
        $this->taxId = (string) $card->tax_id;
        $this->consent = (bool) $card->consent_given;
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function save(CreateClient $create, UpdateClient $update): void
    {
        $this->validate();

        $trainer = auth()->user();

        $attributes = [
            'name' => trim($this->name),
            'phone' => $this->text($this->phone),
            'email' => $this->text($this->email),
            'rate' => Money::fromInput($this->rate),
            'goal' => $this->text($this->goal),
            // The consent checkbox promises health data is not kept without it, so it is not.
            'contraindications' => $this->consent ? $this->text($this->contraindications) : null,
            'guardian' => $this->text($this->guardian),
            'trainer_notes' => $this->text($this->trainerNotes),
            'company_name' => $this->invoice ? trim($this->companyName) : null,
            'tax_id' => $this->invoice ? trim($this->taxId) : null,
            'consent_given' => $this->consent,
        ];

        if ($this->clientId !== null) {
            $card = Client::findOrFail($this->clientId);
            $this->authorize('update', $card);

            $update->handle($trainer, $card, $attributes);
            $message = 'Karta '.$card->name.' zaktualizowana.';
        } else {
            $this->authorize('create', Client::class);

            $card = $create->handle($trainer, $attributes);
            $message = 'Klient '.$card->name.' dodany.';
        }

        $this->close();

        $this->dispatch('client-saved');
        $this->dispatch('toast', message: $message);
    }

    public function render(): View
    {
        $editing = $this->clientId !== null;

        return view('livewire.dialogs.client-dialog', [
            'kicker' => $editing ? 'Edycja karty' : 'Nowa karta',
            'title' => $editing ? $this->name : 'Dodaj klienta',
            'saveLabel' => $editing ? 'Zapisz zmiany' : 'Zapisz klienta',
        ]);
    }

    private function text(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
