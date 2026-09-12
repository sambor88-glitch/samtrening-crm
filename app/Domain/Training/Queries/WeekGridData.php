<?php

namespace App\Domain\Training\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The grid as the screen needs it: which week it is, its seven days, and a row per client.
 */
readonly class WeekGridData
{
    /**
     * @param  list<CarbonImmutable>  $days
     * @param  Collection<int, WeekGridRow>  $rows
     */
    public function __construct(
        public CarbonImmutable $monday,
        public array $days,
        public Collection $rows,
    ) {}

    public function label(): string
    {
        $sunday = $this->monday->addDays(6);

        return $this->monday->month === $sunday->month
            ? $this->monday->format('j').'–'.$sunday->format('j.m.Y')
            : $this->monday->format('j.m').'–'.$sunday->format('j.m.Y');
    }

    public function isEmpty(): bool
    {
        return $this->rows->isEmpty();
    }
}
