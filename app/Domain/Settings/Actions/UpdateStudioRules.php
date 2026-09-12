<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The studio's rules — the owner's alone (decision of 11.09.2026). The check lives here and not
 * only in a hidden form, so calling the action directly is refused too.
 */
class UpdateStudioRules
{
    /** Field => how the log should name it, in the order the screen shows them. */
    private const array LABELS = [
        'reminders_enabled' => 'monit o zaległej płatności',
        'free_cancellation_hours' => 'bezpłatne odwołanie (h)',
        'reminder_threshold_days' => 'monit po (dni)',
        'retention_months' => 'retencja danych (mies.)',
        'ticker_enabled' => 'pasek na górze',
    ];

    public function __construct(private readonly ActivityLogger $log) {}

    /**
     * @param  array<string, mixed>  $rules
     */
    public function handle(User $actor, array $rules): Setting
    {
        Gate::forUser($actor)->authorize('manage-studio-rules');

        $settings = Setting::current();
        $changes = [];

        foreach (self::LABELS as $field => $label) {
            if (! array_key_exists($field, $rules)) {
                continue;
            }

            $before = $settings->{$field};
            $after = is_bool($before) ? (bool) $rules[$field] : (int) $rules[$field];

            if ($before === $after) {
                continue;
            }

            $settings->{$field} = $after;
            $changes[] = $label.': '.$this->readable($before).' → '.$this->readable($after);
        }

        if ($changes === []) {
            return $settings;
        }

        $settings->save();

        // Every change carries the value before and after — that is the whole point of logging
        // a settings screen (docs/SPEC-EKRANY.md ekran 11).
        $this->log->record($actor, 'Zmienił zasady studia', implode(', ', $changes));

        return $settings;
    }

    private function readable(bool|int $value): string
    {
        return match (true) {
            $value === true => 'włączone',
            $value === false => 'wyłączone',
            default => (string) $value,
        };
    }
}
