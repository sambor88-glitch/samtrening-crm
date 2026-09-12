<?php

namespace App\Livewire\Trainer;

use App\Domain\Settings\Actions\UpdateBlikNumber;
use App\Domain\Settings\Actions\UpdateStudioRules;
use App\Domain\Settings\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * "Jak to ma działać." — docs/SPEC-EKRANY.md ekran 11, with the corrections from §13: no Stripe,
 * no automatic payment marking, no BLIK link. The BLIK number is each trainer's own; the studio
 * rules belong to the owner and everybody else sees them read-only.
 */
class Settings extends Component
{
    public string $blik = '';

    public bool $remindersEnabled = true;

    public int $freeCancellationHours = 24;

    public int $reminderThresholdDays = 14;

    public int $retentionMonths = 60;

    public bool $tickerEnabled = true;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'blik' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()-]+$/'],
            'remindersEnabled' => ['boolean'],
            'freeCancellationHours' => ['required', 'integer', 'min:0', 'max:168'],
            'reminderThresholdDays' => ['required', 'integer', 'min:1', 'max:365'],
            'retentionMonths' => ['required', 'integer', 'min:12', 'max:600'],
            'tickerEnabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'blik.regex' => 'Numer BLIK to numer telefonu — cyfry, spacje i znak +.',
            'freeCancellationHours.max' => 'Więcej niż tydzień to już nie jest odwołanie.',
            'reminderThresholdDays.min' => 'Monit tego samego dnia to nie monit.',
            'retentionMonths.min' => 'Rok to minimum — rozliczenia muszą przeżyć kontrolę.',
        ];
    }

    public function mount(): void
    {
        $settings = Setting::current();

        $this->blik = (string) (auth()->user()->blik_number ?? '');
        $this->remindersEnabled = $settings->reminders_enabled;
        $this->freeCancellationHours = $settings->free_cancellation_hours;
        $this->reminderThresholdDays = $settings->reminder_threshold_days;
        $this->retentionMonths = $settings->retention_months;
        $this->tickerEnabled = $settings->ticker_enabled;
    }

    public function saveBlik(UpdateBlikNumber $update): void
    {
        $this->validateOnly('blik');

        $update->handle(auth()->user(), $this->blik);

        $this->dispatch('toast', message: blank($this->blik)
            ? 'Numer BLIK wyczyszczony. Bez niego nie wyślesz wiadomości.'
            : 'Twój numer BLIK zapisany. Podstawia się w każdej wiadomości o płatności.');
    }

    public function saveRules(UpdateStudioRules $update): void
    {
        $this->validate();

        $update->handle(auth()->user(), [
            'reminders_enabled' => $this->remindersEnabled,
            'free_cancellation_hours' => $this->freeCancellationHours,
            'reminder_threshold_days' => $this->reminderThresholdDays,
            'retention_months' => $this->retentionMonths,
            'ticker_enabled' => $this->tickerEnabled,
        ]);

        $this->dispatch('toast', message: 'Zasady studia zapisane. Obowiązują od zaraz.');
    }

    public function render(): View
    {
        return view('livewire.trainer.settings', [
            'canManage' => Gate::allows('manage-studio-rules'),
            'owner' => auth()->user()->is_owner,
        ]);
    }
}
