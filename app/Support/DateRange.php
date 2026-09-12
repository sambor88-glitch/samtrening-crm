<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A reporting range written as a date prefix: "2026-09" is September 2026, "2026" the whole year.
 * Shared by the trainer's earnings, the studio dashboard and the trainer list. Balances and
 * arrears never use it — a debt does not belong to a month.
 */
class DateRange
{
    private function __construct(
        private readonly string $prefix,
        private readonly CarbonImmutable $start,
        private readonly CarbonImmutable $end,
    ) {}

    /**
     * @throws InvalidArgumentException when the prefix is neither "YYYY" nor "YYYY-MM"
     */
    public static function fromPrefix(string $prefix): self
    {
        $timezone = config('app.timezone');

        if (preg_match('/^\d{4}$/', $prefix)) {
            $start = CarbonImmutable::create((int) $prefix, 1, 1, 0, 0, 0, $timezone);

            return new self($prefix, $start, $start->endOfYear());
        }

        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $prefix, $matches)) {
            $start = CarbonImmutable::create((int) $matches[1], (int) $matches[2], 1, 0, 0, 0, $timezone);

            return new self($prefix, $start, $start->endOfMonth());
        }

        throw new InvalidArgumentException("Not a month or year prefix: {$prefix}");
    }

    /**
     * The month that is current on the studio's calendar.
     */
    public static function currentMonth(): self
    {
        return self::fromPrefix(now()->format('Y-m'));
    }

    /**
     * The ranges the screens offer: the last three months and the year so far. Shared by the
     * trainer's earnings and the studio dashboard, so the two never drift apart.
     *
     * @return array<string, string>
     */
    public static function recent(): array
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

    /**
     * "Wrzesień 2026" or "Cały 2026" — whichever this range is.
     */
    public function label(): string
    {
        return $this->isYear() ? 'Cały '.$this->prefix : PolishMonth::withYear($this->start);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function isYear(): bool
    {
        return strlen($this->prefix) === 4;
    }

    /**
     * First moment of the range, in the studio's time zone.
     */
    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    /**
     * Last moment of the range (23:59:59.999999 on the last day), in the studio's time zone.
     */
    public function end(): CarbonImmutable
    {
        return $this->end;
    }

    /**
     * First day as Y-m-d. Compare DATE columns with firstDay()/lastDay(), not with start()/end() —
     * a date string against a datetime string sorts differently in SQLite.
     */
    public function firstDay(): string
    {
        return $this->start->toDateString();
    }

    /**
     * Last day as Y-m-d.
     */
    public function lastDay(): string
    {
        return $this->end->toDateString();
    }
}
