<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\SmsCount;
use App\Domain\Messaging\TemplateRenderer;
use App\Domain\Team\Models\User;
use App\Support\Plural;

/**
 * The nudge to somebody who has stopped coming. Not about money — nothing is owed here — which is
 * why it is a separate text from the reminder and says so: there are free slots this week.
 */
class SendReEngagement
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly SendSms $sms,
        private readonly ActivityLogger $log,
    ) {}

    public function handle(User $trainer, Client $client, int $silentDays): SmsCount
    {
        $template = (string) MessageTemplate::query()->where('key', 're_engagement')->value('body');

        $count = $this->sms->handle(
            sender: $trainer,
            client: $client,
            text: $this->renderer->render($template, $this->renderer->contextFor($client)),
            subject: 'zaczepka po ciszy',
            actor: $trainer,
        );

        $this->log->record(
            $trainer,
            'Zaczepił po ciszy',
            $client->name.' · '.Plural::of($silentDays, 'dzień', 'dni', 'dni').' bez sesji',
        );

        return $count;
    }
}
