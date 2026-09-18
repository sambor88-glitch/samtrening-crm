<?php

namespace App\Domain\Agent\Queries;

use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One month of the studio, for the dashboard — docs/AGENT-API.md §5.
 *
 * Two of these three money figures are flows and one is a level, which is the whole reason the
 * spec spells them out: `due` is what the month's training earned, `paid` is what came through
 * the door during the month whatever it was for, and `outstanding` is the pile of debt as it
 * stands today. `due - paid` is not `outstanding` and never was — a client can pay up front or
 * clear last spring's arrears, and both break the subtraction.
 */
class MonthSummary
{
    public function __construct(private readonly Outstanding $outstanding) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(DateRange $range): array
    {
        $counts = $this->counts($range);
        $perClient = $this->perClient($range);

        return [
            'month' => $range->prefix(),
            'generated_at' => CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
            'currency' => 'PLN',
            'sessions' => [
                'done' => $counts['done'],
                'planned' => $counts['planned'],
                'cancelled' => $counts['cancelled'],
            ],
            'revenue_minor' => [
                'due' => $counts['due'],
                'paid' => $this->paidIn($range),
                // A level, not a flow: everything anybody owes right now, whatever month it is
                // from. Cumulative on purpose — a debt does not belong to a month.
                'outstanding' => $this->outstanding->totalForStudio(),
            ],
            'by_client' => $perClient,
            'by_day' => $this->byDay($range),
        ];
    }

    /**
     * The month's three counts and what its training earned, in one grouped query.
     *
     * `planned` is every session written down for the month bar the cancellations, which is what
     * the spec defines it as. Note that it cannot mean sessions still to come: the CRM holds only
     * sessions that already happened (README) — see docs/AGENT-API.md §8.
     *
     * @return array{done: int, planned: int, cancelled: int, due: int}
     */
    private function counts(DateRange $range): array
    {
        $rows = TrainingSession::query()
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->groupBy('kind')
            ->selectRaw('kind, count(*) as sessions')
            // Waived is training given away; nothing is due for it.
            ->selectRaw('sum(case when payment_status = ? then 0 else price end) as due', [PaymentStatus::Waived->value])
            ->get()
            ->keyBy('kind');

        $count = fn (SessionKind $kind) => (int) ($rows[$kind->value]->sessions ?? 0);

        return [
            'done' => $count(SessionKind::Completed),
            'planned' => $count(SessionKind::Completed) + $count(SessionKind::NoShow),
            'cancelled' => $count(SessionKind::Cancelled),
            'due' => (int) ($rows[SessionKind::Completed->value]->due ?? 0),
        ];
    }

    /**
     * Money that actually arrived during the month, from both places it can come from: a client
     * settling sessions, and a client paying up front.
     *
     * A session settled in cash brings in the part no prepayment had already covered — the rest
     * of it came in earlier, as the prepayment, and counting the whole price here would count
     * that money twice.
     */
    private function paidIn(DateRange $range): int
    {
        $settled = (int) $this->settledIn($range)->sum(DB::raw('price - prepaid_amount'));

        $prepaid = (int) DB::table('prepayments')
            ->whereNull('deleted_at')
            ->whereBetween('paid_on', [$range->firstDay(), $range->lastDay()])
            ->sum('amount');

        return $settled + $prepaid;
    }

    /**
     * Sessions whose money landed inside the range.
     *
     * Rows settled before `paid_at` existed have nothing better to go on than the session date,
     * so they fall back to it. That is an approximation, and only for history: every payment
     * marked from now on carries the real moment (docs/AGENT-API.md §8).
     *
     * @return Builder<TrainingSession>
     */
    private function settledIn(DateRange $range): Builder
    {
        return TrainingSession::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where(fn ($query) => $query
                ->whereBetween('paid_at', [$range->start(), $range->end()])
                ->orWhere(fn ($legacy) => $legacy
                    ->whereNull('paid_at')
                    ->whereBetween('date', [$range->firstDay(), $range->lastDay()])));
    }

    /**
     * Per client, for the month. Only clients with something in the month appear — a card with no
     * sessions and no payments has nothing to say about it.
     *
     * @return list<array<string, mixed>>
     */
    private function perClient(DateRange $range): array
    {
        $sessions = TrainingSession::query()
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->groupBy('client_id')
            ->selectRaw('client_id')
            ->selectRaw('sum(case when kind = ? then 1 else 0 end) as done', [SessionKind::Completed->value])
            ->selectRaw('sum(case when kind = ? then 0 else 1 end) as not_cancelled', [SessionKind::Cancelled->value])
            ->selectRaw('sum(case when kind = ? and payment_status <> ? then price else 0 end) as due', [
                SessionKind::Completed->value,
                PaymentStatus::Waived->value,
            ])
            ->get()
            ->keyBy('client_id');

        $settled = $this->settledIn($range)
            ->groupBy('client_id')
            ->selectRaw('client_id, sum(price - prepaid_amount) as paid')
            ->pluck('paid', 'client_id');

        $prepaid = DB::table('prepayments')
            ->whereNull('deleted_at')
            ->whereBetween('paid_on', [$range->firstDay(), $range->lastDay()])
            ->groupBy('client_id')
            ->selectRaw('client_id, sum(amount) as paid')
            ->pluck('paid', 'client_id');

        $ids = collect($sessions->keys())
            ->merge($settled->keys())
            ->merge($prepaid->keys())
            ->unique()
            ->sort()
            ->values();

        return $ids->map(fn ($clientId) => [
            'id' => (int) $clientId,
            'done' => (int) ($sessions[$clientId]->done ?? 0),
            'planned' => (int) ($sessions[$clientId]->not_cancelled ?? 0),
            'due_minor' => (int) ($sessions[$clientId]->due ?? 0),
            'paid_minor' => (int) ($settled[$clientId] ?? 0) + (int) ($prepaid[$clientId] ?? 0),
        ])->all();
    }

    /**
     * Every day of the month, zeroes included — the dashboard draws bars from this and will not
     * guess at gaps (docs/AGENT-API.md §5).
     *
     * @return list<array<string, mixed>>
     */
    private function byDay(DateRange $range): array
    {
        $rows = TrainingSession::query()
            ->whereBetween('date', [$range->firstDay(), $range->lastDay()])
            ->where('kind', SessionKind::Completed)
            ->groupBy('date')
            ->selectRaw('date, count(*) as done')
            ->selectRaw('sum(case when payment_status = ? then 0 else price end) as revenue', [PaymentStatus::Waived->value])
            ->get()
            // SQLite hands back "2026-09-01", MySQL can hand back a full datetime; the key has to
            // be the plain day either way.
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        $days = [];

        for ($day = $range->start(); $day->lessThanOrEqualTo($range->end()); $day = $day->addDay()) {
            $key = $day->toDateString();

            $days[] = [
                'date' => $key,
                'done' => (int) ($rows[$key]->done ?? 0),
                'revenue_minor' => (int) ($rows[$key]->revenue ?? 0),
            ];
        }

        return $days;
    }
}
