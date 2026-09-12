<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Earnings as EarningsCalculator;
use App\Domain\Billing\Export\SessionCsvExport;
use App\Domain\Training\Queries\SessionHistory;
use App\Support\DateRange;
use App\Support\Plural;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Ile zarobiłeś." — docs/SPEC-EKRANY.md ekran 9. Headings follow the chosen range: no screen
 * in this app says "wrzesień" when it is showing July (§13).
 */
class Earnings extends Component
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

    #[On('session-logged')]
    public function refresh(): void {}

    /**
     * The accountant's file: semicolons, a BOM and no session notes (docs/START-TUTAJ.md §10).
     */
    public function exportCsv(SessionCsvExport $export): StreamedResponse
    {
        $range = $this->resolve($this->range);
        $file = $export->forTrainer(auth()->user(), $range);

        $this->dispatch(
            'toast',
            message: 'Eksport CSV — '.$this->label($range).', '
                .Plural::of($file->rows, 'wiersz', 'wiersze', 'wierszy').'.',
        );

        // Straight to the output stream: no echo (the php preset forbids it) and no temporary
        // file on disk (the security preset forbids that, for good reason).
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

    public function render(EarningsCalculator $earnings, SessionHistory $history): View
    {
        $trainer = auth()->user();
        $range = $this->resolve($this->range);

        return view('livewire.trainer.earnings', [
            'summary' => $earnings->forTrainer($trainer, $range),
            'sessions' => $history->inRange($trainer, $range),
            'label' => $this->label($range),
            'ranges' => DateRange::recent(),
            'isYear' => $range->isYear(),
        ]);
    }

    /**
     * A prefix that makes no sense — a hand-typed address, a stale link — falls back to the
     * month everyone is in.
     */
    private function resolve(string $prefix): DateRange
    {
        try {
            return DateRange::fromPrefix($prefix);
        } catch (InvalidArgumentException) {
            return DateRange::currentMonth();
        }
    }

    private function label(DateRange $range): string
    {
        return $range->label();
    }
}
