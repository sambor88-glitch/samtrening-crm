<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\SmsCount;
use App\Domain\Messaging\TemplateRenderer;
use App\Domain\Team\Models\User;
use App\Support\Money;

/**
 * The nudge about money that has been owed too long. Sent by hand from the dashboard, or by the
 * nightly run with nobody logged in — then `$actor` is null and the log says "System".
 */
class SendReminder
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly SendSms $sms,
        private readonly ActivityLogger $log,
        private readonly Balance $balance,
    ) {}

    public function handle(?User $actor, Client $client): SmsCount
    {
        $template = (string) MessageTemplate::query()->where('key', 'reminder')->value('body');

        // Whoever triggered it, the number in the text is the trainer's who runs this client.
        $count = $this->sms->handle(
            sender: $client->trainer,
            client: $client,
            text: $this->renderer->render($template, $this->renderer->contextFor($client)),
            subject: 'monit o płatności',
            actor: $actor,
        );

        // Not mass assignable on purpose: the weekly limit is the system's bookkeeping, not
        // something a form may set.
        $client->forceFill(['last_reminder_at' => now()])->save();

        $this->log->record(
            $actor,
            'Wysłał monit',
            $client->name.' · '.Money::format($this->balance->forClient($client)),
        );

        return $count;
    }
}
