<?php

namespace App\Livewire\Admin;

use App\Domain\Team\Actions\BlockTrainer;
use App\Domain\Team\Actions\ResendInvitation;
use App\Domain\Team\Actions\ResetTrainerPassword;
use App\Domain\Team\Models\User;
use App\Domain\Team\Queries\TeamRoster;
use App\Support\DateRange;
use App\Support\PolishMonth;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * "Trzy osoby. Nie statyści." — docs/SPEC-EKRANY.md ekran 13. Every action here belongs to the
 * owner, and every one of them leaves a line in the log.
 */
class TeamList extends Component
{
    #[On('trainer-invited')]
    public function refresh(): void {}

    public function resetPassword(int $trainer): void
    {
        $account = User::findOrFail($trainer);

        $this->authorize('resetPassword', $account);

        app(ResetTrainerPassword::class)->handle(auth()->user(), $account);

        $this->dispatch('toast', message: 'Link do ustawienia hasła poszedł na '.$account->email.'.');
    }

    public function toggleBlock(int $trainer, bool $blocked): void
    {
        $account = User::findOrFail($trainer);

        $this->authorize('block', $account);

        try {
            app(BlockTrainer::class)->handle(auth()->user(), $account, $blocked);
        } catch (RuntimeException $refused) {
            $this->dispatch('toast', message: $refused->getMessage(), variant: 'error');

            return;
        }

        $this->dispatch(
            'toast',
            message: $blocked
                ? $account->name.' zablokowany. Wylogowany ze wszystkich urządzeń.'
                : $account->name.' znowu ma dostęp.',
        );
    }

    public function resendInvitation(int $trainer): void
    {
        $account = User::findOrFail($trainer);

        $this->authorize('create', User::class);

        try {
            app(ResendInvitation::class)->handle(auth()->user(), $account);
        } catch (RuntimeException $refused) {
            $this->dispatch('toast', message: $refused->getMessage(), variant: 'error');

            return;
        }

        $this->dispatch(
            'toast',
            message: 'Nowe zaproszenie poszło na '.$account->email.'. Poprzedni link przestał działać.',
        );
    }

    public function render(TeamRoster $roster): View
    {
        $this->authorize('viewAny', User::class);

        $month = DateRange::currentMonth();

        return view('livewire.admin.team-list', [
            'rows' => $roster->forStudio($month),
            'monthName' => PolishMonth::name($month->start()),
        ]);
    }
}
