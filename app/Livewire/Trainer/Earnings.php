<?php

namespace App\Livewire\Trainer;

use App\Domain\Billing\Earnings as EarningsCalculator;
use App\Domain\Training\Queries\SessionHistory;
use App\Support\DateRange;
use App\Support\PolishMonth;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

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
     * The file itself is SC-28.
     */
    public function exportCsv(): void
    {
        $this->dispatch('toast', message: 'Eksport CSV dokłada zgłoszenie SC-28 — na razie przycisk czeka.');
    }

    public function render(EarningsCalculator $earnings, SessionHistory $history): View
    {
        $trainer = auth()->user();
        $range = $this->resolve($this->range);

        return view('livewire.trainer.earnings', [
            'summary' => $earnings->forTrainer($trainer, $range),
            'sessions' => $history->inRange($trainer, $range),
            'label' => $this->label($range),
            'ranges' => $this->ranges(),
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
        return $range->isYear() ? 'Cały '.$range->prefix() : PolishMonth::withYear($range->start());
    }

    /**
     * The last three months and the year so far.
     *
     * @return array<string, string>
     */
    private function ranges(): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $ranges = [];

        foreach (range(0, 2) as $monthsBack) {
            $month = $now->subMonths($monthsBack);
            $ranges[$month->format('Y-m')] = PolishMonth::withYear($month);
        }

        $ranges[$now->format('Y')] = 'Cały '.$now->format('Y');

        return $ranges;
    }
}
