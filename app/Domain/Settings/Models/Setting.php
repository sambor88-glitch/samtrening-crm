<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Studio-wide rules. The table holds exactly one row, created by the seeder.
 */
#[Fillable([
    'sms_provider', 'reminders_enabled', 'reminder_threshold_days', 'free_cancellation_hours',
    'retention_months', 'ticker_enabled',
])]
class Setting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reminders_enabled' => 'boolean',
            'reminder_threshold_days' => 'integer',
            'free_cancellation_hours' => 'integer',
            'retention_months' => 'integer',
            'ticker_enabled' => 'boolean',
        ];
    }

    /**
     * The studio's single settings row.
     */
    public static function current(): self
    {
        return static::query()->firstOrFail();
    }
}
