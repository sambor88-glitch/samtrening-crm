<?php

namespace App\Livewire\Trainer;

use App\Domain\Calendar\Queries\PendingSessions;
use App\Domain\Calendar\SessionCandidate;
use App\Domain\Training\Actions\LogSession;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use RuntimeException;

/**
 * "Z kalendarza" — the trainings the diary knows about and the CRM does not, offered
 * for logging in one click (SC-65).
 *
 * The list is a proposal, never a decision. The calendar has no idea a client called
 * off half an hour before, so nothing here logs itself; what is ticked is what gets
 * logged, and the price stays editable because the rate on the card is a starting
 * point, not a rule.
 */
class CalendarSessions extends Component
{
    /** Event keys the trainer wants logged. */
    public array $selected = [];

    /** Event key => price in złoty, as typed. */
    public array $prices = [];

    /** Set when Google refuses, so the screen can say so instead of dying. */
    public ?string $failure = null;

    public function mount(PendingSessions $pending): void
    {
        $candidates = $this->candidates($pending);

        // Everything the CRM could match is ticked; the trainer unticks what did not
        // happen, which is the shorter list.
        $this->selected = $candidates->filter->isMatched()->map->key()->values()->all();

        $this->prices = $candidates->filter->isMatched()
            ->mapWithKeys(fn (SessionCandidate $c) => [$c->key() => Money::toInput($c->suggestedPrice())])
            ->all();
    }

    /**
     * Ask Google again rather than reading the few-minute-old answer.
     */
    public function refreshFromCalendar(PendingSessions $pending): void
    {
        $pending->forget(auth()->user());

        $this->mount($pending);
    }

    /**
     * Logs the ticked trainings. Everything is re-read from the server first: the
     * keys arrive from a browser, and a swapped one must bounce off the same query
     * that built the list, not off the screen (docs/START-TUTAJ.md §7).
     */
    public function log(PendingSessions $pending, LogSession $action): void
    {
        $candidates = $this->candidates($pending)
            ->filter->isMatched()
            ->filter(fn (SessionCandidate $c) => in_array($c->key(), $this->selected, true));

        if ($candidates->isEmpty()) {
            $this->dispatch('toast', message: 'Nie zaznaczono żadnej sesji.', variant: 'error');

            return;
        }

        $logged = 0;

        foreach ($candidates as $candidate) {
            $action->handle(auth()->user(), $candidate->client, [
                'date' => $candidate->date(),
                'service' => 'Trening personalny 1:1',
                'price' => $this->priceFor($candidate),
                'kind' => SessionKind::Completed,
                'payment_status' => PaymentStatus::Balance,
                // So the activity log tells a confirmed session apart from a typed one.
                'source' => 'calendar',
            ]);

            $logged++;
        }

        // The list is built from what is not in the CRM, so what was just logged has
        // to leave it — otherwise a second click would charge for it twice.
        $pending->forget(auth()->user());
        $this->mount($pending);

        $this->dispatch('session-logged');
        $this->dispatch('toast', message: $this->confirmation($logged));
    }

    public function render(PendingSessions $pending): View
    {
        $candidates = $this->candidates($pending);

        return view('livewire.trainer.calendar-sessions', [
            'matched' => $candidates->filter->isMatched()->values(),
            'unmatched' => $candidates->reject->isMatched()->values(),
            'configured' => $pending->isConfigured(),
        ]);
    }

    /**
     * @return Collection<int, SessionCandidate>
     */
    private function candidates(PendingSessions $pending): Collection
    {
        try {
            $this->failure = null;

            return $pending->forTrainer(auth()->user());
        } catch (RuntimeException $e) {
            // A diary that will not answer must not take the sessions screen down
            // with it — docs/START-TUTAJ.md §9.
            Log::warning('Kalendarz nie odpowiedział: '.$e->getMessage());

            $this->failure = 'Nie udało się odczytać kalendarza. Sesje wbijesz ręcznie, jak zwykle.';

            return collect();
        }
    }

    /**
     * What the trainer typed, or the client's rate when the field was left alone.
     * An unreadable amount falls back to the rate rather than logging a zero.
     */
    private function priceFor(SessionCandidate $candidate): int
    {
        $typed = $this->prices[$candidate->key()] ?? null;

        if (! is_string($typed) && ! is_int($typed)) {
            return $candidate->suggestedPrice();
        }

        try {
            return Money::fromInput($typed);
        } catch (\InvalidArgumentException) {
            return $candidate->suggestedPrice();
        }
    }

    private function confirmation(int $logged): string
    {
        return match (true) {
            $logged === 1 => 'Wbita 1 sesja z kalendarza.',
            $logged < 5 => "Wbite {$logged} sesje z kalendarza.",
            default => "Wbitych {$logged} sesji z kalendarza.",
        };
    }
}
