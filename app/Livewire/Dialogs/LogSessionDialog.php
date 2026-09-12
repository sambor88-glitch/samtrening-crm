<?php

namespace App\Livewire\Dialogs;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\ClientOptions;
use App\Domain\Settings\Models\Setting;
use App\Domain\Training\Actions\LogSession;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\Service;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;
use App\Support\Plural;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Wbij sesję" — docs/SPEC-EKRANY.md §Wbij sesję. The dialog collects, the action writes; a
 * duplicate is warned about but never blocked, because two sessions in one day do happen.
 */
class LogSessionDialog extends Component
{
    public bool $open = false;

    public ?int $clientId = null;

    public string $kind = 'completed';

    public string $service = 'Trening personalny 1:1';

    public string $date = '';

    public string $price = '';

    public string $notes = '';

    /** Goes to the client's card, not to the session. */
    public string $plan = '';

    public string $settlement = 'balance';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'clientId' => ['required', Rule::exists('clients', 'id')],
            'kind' => ['required', Rule::enum(SessionKind::class)],
            'service' => ['required', Rule::enum(Service::class)],
            'date' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'plan' => ['nullable', 'string', 'max:500'],
            'settlement' => ['required', Rule::enum(PaymentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'clientId.required' => 'Wybierz klienta.',
            'date.required' => 'Podaj datę sesji.',
            'price.required' => 'Podaj kwotę.',
            'price.numeric' => 'Kwota to liczba w złotych.',
        ];
    }

    #[On('log-session')]
    public function openFor(?int $client = null): void
    {
        $this->authorize('create', TrainingSession::class);

        $this->reset();
        $this->resetValidation();

        $this->date = now()->toDateString();
        $this->clientId = $client;
        $this->syncClient();
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function updatedClientId(): void
    {
        $this->syncClient();
    }

    public function updatedService(): void
    {
        $this->price = Money::toInput($this->suggestedPrice());
    }

    /**
     * A cancellation starts at nothing charged; a session held starts at the agreed amount.
     */
    public function updatedKind(): void
    {
        if (SessionKind::from($this->kind) === SessionKind::Completed) {
            $this->settlement = PaymentStatus::Balance->value;
            $this->price = Money::toInput($this->suggestedPrice());

            return;
        }

        $this->settlement = PaymentStatus::Waived->value;
        $this->price = '0';
    }

    public function save(LogSession $logSession): void
    {
        $this->validate();

        $client = Client::findOrFail($this->clientId);

        $this->authorize('view', $client);
        $this->authorize('create', TrainingSession::class);

        $kind = SessionKind::from($this->kind);
        $status = PaymentStatus::from($this->settlement);
        $price = $status === PaymentStatus::Waived ? 0 : Money::fromInput($this->price);

        $logSession->handle(auth()->user(), $client, [
            'date' => $this->date,
            'service' => $this->service,
            'price' => $price,
            'kind' => $kind,
            'payment_status' => $status,
            'notes' => trim($this->notes) ?: null,
            'next_session_plan' => trim($this->plan) ?: null,
        ]);

        $message = $this->confirmation($kind, $client->name, $price, $status);

        $this->close();

        $this->dispatch('session-logged');
        $this->dispatch('toast', message: $message);
    }

    public function render(ClientOptions $clients): View
    {
        $held = SessionKind::from($this->kind) === SessionKind::Completed;
        $duplicate = $this->duplicateWarning();

        return view('livewire.dialogs.log-session-dialog', [
            'clients' => $clients->forTrainer(auth()->user()),
            'services' => Service::options(),
            'rateHint' => $this->client()
                ? 'Stawka klienta: '.Money::format($this->client()->rate)
                : 'Stawka podpowie się po wybraniu klienta.',
            'settlementLabel' => $held ? 'Rozliczenie' : 'Czy naliczasz?',
            'settlementOptions' => $held ? $this->heldOptions() : $this->missedOptions(),
            'duplicate' => $duplicate,
            'saveLabel' => $duplicate ? 'Zapisz mimo to' : 'Zapisz sesję',
        ]);
    }

    private function client(): ?Client
    {
        return $this->clientId ? Client::find($this->clientId) : null;
    }

    private function syncClient(): void
    {
        $client = $this->client();

        $this->price = Money::toInput($this->suggestedPrice());
        $this->plan = (string) $client?->next_session_plan;
    }

    private function suggestedPrice(): int
    {
        return Service::from($this->service)->priceFor($this->client()?->rate ?? 0);
    }

    /**
     * Rule 2 from the business rules: say something, change the button, save anyway.
     */
    private function duplicateWarning(): ?string
    {
        $client = $this->client();

        if (! $client || $this->date === '') {
            return null;
        }

        $count = $client->sessions()->where('date', $this->date)->count();

        if ($count === 0) {
            return null;
        }

        return Str::before($client->name, ' ').' ma już '
            .Plural::of($count, 'wpis', 'wpisy', 'wpisów')
            .' z tą datą. Sprawdź, czy nie wbijasz tego samego dwa razy.';
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    private function heldOptions(): array
    {
        return [
            PaymentStatus::Paid->value => [
                'label' => 'Zapłacone na miejscu',
                'hint' => 'Gotówka albo BLIK od razu po sesji. Saldo bez zmian.',
            ],
            PaymentStatus::Balance->value => [
                'label' => 'Na saldo',
                'hint' => 'Doliczam do salda, link wyślę później albo zbiorczo na koniec miesiąca.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    private function missedOptions(): array
    {
        $hours = (int) (Setting::query()->value('free_cancellation_hours') ?? 24);

        return [
            PaymentStatus::Waived->value => [
                'label' => 'Nie naliczam',
                'hint' => 'Odwołanie na '.$hours.' h przed sesją jest bezpłatne — tak jak obiecujecie na stronie.',
            ],
            PaymentStatus::Balance->value => [
                'label' => 'Naliczam — na saldo',
                'hint' => 'Odwołanie po terminie albo brak informacji. Kwotę wpisz wyżej.',
            ],
            PaymentStatus::Paid->value => [
                'label' => 'Naliczam — zapłacone',
                'hint' => 'Klient uregulował od razu.',
            ],
        ];
    }

    private function confirmation(SessionKind $kind, string $name, int $price, PaymentStatus $status): string
    {
        $what = match ($kind) {
            SessionKind::Completed => 'Sesja wbita',
            SessionKind::Cancelled => 'Odwołanie zapisane',
            SessionKind::NoShow => 'Nieobecność zapisana',
        };

        $ending = match ($status) {
            PaymentStatus::Waived => 'Bez naliczenia.',
            PaymentStatus::Paid => 'Opłacone na miejscu.',
            default => 'Doliczone do salda.',
        };

        return $what.' — '.$name.', '.Money::format($price).'. '.$ending;
    }
}
