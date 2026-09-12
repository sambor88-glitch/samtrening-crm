<?php

namespace App\Console\Commands;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Domain\Messaging\Actions\SendReminder;
use App\Domain\Messaging\SmsNotPossible;
use App\Domain\Settings\Models\Setting;
use Illuminate\Console\Command;

/**
 * The daily 10:00 run — docs/START-TUTAJ.md §10. One reminder per client per week at most, only
 * for debts past the studio's threshold, and only while the studio has reminders switched on.
 */
class SendDueReminders extends Command
{
    protected $signature = 'samtrening:monity';

    protected $description = 'Wysyła monity SMS do klientów po terminie (raz na 7 dni na klienta).';

    public function handle(Outstanding $outstanding, SendReminder $reminder, ActivityLogger $log): int
    {
        $settings = Setting::query()->first();

        if (! $settings?->reminders_enabled) {
            $this->info('Monity są wyłączone w ustawieniach studia — nic nie wysyłam.');

            return self::SUCCESS;
        }

        $threshold = (int) $settings->reminder_threshold_days;
        $sent = 0;

        foreach ($outstanding->forStudio() as $row) {
            if (! $row->isOverdue($threshold) || $this->remindedThisWeek($row)) {
                continue;
            }

            try {
                $reminder->handle(null, $row->client);
                $sent++;
            } catch (SmsNotPossible $blocked) {
                // A trainer with no BLIK number, or a client with no phone: say so once, in the
                // log, rather than failing the whole run.
                $log->record(null, 'Monit pominięty', $row->client->name.' · '.$blocked->getMessage());
            }
        }

        $this->info("Wysłane monity: {$sent}.");

        return self::SUCCESS;
    }

    private function remindedThisWeek(OutstandingRow $row): bool
    {
        return $row->client->last_reminder_at !== null
            && $row->client->last_reminder_at->greaterThan(now()->subDays(7));
    }
}
