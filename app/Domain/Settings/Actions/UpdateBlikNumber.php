<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Team\Models\User;

/**
 * Everybody sets their own — the money goes to the trainer's phone, not to the studio's
 * (docs/START-TUTAJ.md §9). Nobody edits somebody else's number here.
 */
class UpdateBlikNumber
{
    public function __construct(private readonly ActivityLogger $log) {}

    public function handle(User $trainer, ?string $number): User
    {
        $before = $trainer->blik_number;
        $number = blank($number) ? null : trim($number);

        if ($before === $number) {
            return $trainer;
        }

        $trainer->update(['blik_number' => $number]);

        $this->log->record(
            $trainer,
            'Zmienił numer BLIK',
            ($before ?: 'brak').' → '.($number ?: 'brak'),
        );

        return $trainer;
    }
}
