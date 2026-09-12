<?php

namespace App\Livewire\Admin;

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Earnings;
use App\Domain\Billing\Export\SessionCsvExport;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Domain\Team\Queries\TeamRoster;
use App\Domain\Team\Queries\TeamRosterRow;
use App\Support\DateRange;
use App\Support\Plural;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Studio w liczbach." — docs/SPEC-EKRANY.md ekran 12. The range moves the studio's work; the
 * outstanding pile deliberately stays put, because a debt does not belong to a month.
 */
class StudioDashboard extends Component
{
    #[Url(as: 'zakres', except: '')]
    public string $range = '';

    public function mount(): void
    {
        $this->range = $this->resolve($this->range)->prefix();
    }

    public function show(string $prefix): void
    {
        $this->range = $this->resolve($prefix)->prefix();
    }

    public function exportCsv(SessionCsvExport $export): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $range = $this->resolve($this->range);
        $file = $export->forStudio(auth()->user(), $range);

        $this->dispatch(
            'toast',
            message: 'Eksport studia — '.$range->label().', '
                .Plural::of($file->rows, 'wiersz', 'wiersze', 'wierszy').'.',
        );

        return response()->streamDownload(
            function () use ($file) {
                $output = fopen('php://output', 'w');
                fwrite($output, $file->contents);
                fclose($output);
            },
            $file->name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function render(Earnings $earnings, Outstanding $outstanding, TeamRoster $roster): View
    {
        $this->authorize('viewAny', User::class);

        $range = $this->resolve($this->range);
        $studio = $earnings->forStudio($range);
        $team = $roster->forStudio($range);

        return view('livewire.admin.studio-dashboard', [
            // Not "range": that name belongs to the public property, and Livewire would hand the
            // view the string instead of the object.
            'current' => $range,
            'ranges' => DateRange::recent(),
            'studio' => $studio,
            'team' => $team,
            'mostSessions' => (int) $team->max(fn (TeamRosterRow $row) => $row->sessions),
            'clients' => Client::query()->where('archived', false)->count(),
            'outstanding' => $outstanding->totalForStudio(),
            'trainers' => User::query()->where('status', UserStatus::Active)->count(),
            'entries' => ActivityEntry::query()->orderByDesc('happened_at')->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    private function resolve(string $prefix): DateRange
    {
        try {
            return DateRange::fromPrefix($prefix);
        } catch (InvalidArgumentException) {
            return DateRange::currentMonth();
        }
    }
}
