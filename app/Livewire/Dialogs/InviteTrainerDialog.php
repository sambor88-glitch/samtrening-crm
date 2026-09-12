<?php

namespace App\Livewire\Dialogs;

use App\Domain\Team\Actions\InviteTrainer;
use App\Domain\Team\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Zaproś trenera" — docs/SPEC-EKRANY.md §Dialogs. Two fields the studio cannot do without and
 * one it can; the rest the trainer fills in themselves after activating.
 */
class InviteTrainerDialog extends Component
{
    public bool $open = false;

    public string $name = '';

    public string $email = '';

    public string $specialty = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'specialty' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Podaj imię i nazwisko.',
            'email.required' => 'Podaj adres e-mail.',
            'email.email' => 'To nie wygląda na adres e-mail.',
            'email.unique' => 'Ten adres już ma konto w studiu.',
        ];
    }

    #[On('invite-trainer')]
    public function openDialog(): void
    {
        $this->authorize('create', User::class);

        $this->reset();
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function save(InviteTrainer $invite): void
    {
        $this->authorize('create', User::class);

        $this->validate();

        $trainer = $invite->handle(auth()->user(), [
            'name' => trim($this->name),
            'email' => trim($this->email),
            'specialty' => trim($this->specialty) ?: null,
        ]);

        $this->close();

        $this->dispatch('trainer-invited');
        $this->dispatch('toast', message: 'Zaproszenie poszło na '.$trainer->email.'. Link ważny 7 dni.');
    }

    public function render(): View
    {
        return view('livewire.dialogs.invite-trainer-dialog');
    }
}
