<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\ClientFile;
use App\Domain\Messaging\Actions\SendSms;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Messaging\SmsCount;
use App\Domain\Messaging\TemplateRenderer;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Sends the client a link that stops working after two weeks. Laravel's signed URLs expire on
 * their own, so there is no token table to keep tidy and nothing to sweep up afterwards.
 */
class SendClientFile
{
    public const int LINK_DAYS = 14;

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly SendSms $sms,
        private readonly ActivityLogger $log,
    ) {}

    public function handle(User $trainer, ClientFile $file): SmsCount
    {
        $link = URL::temporarySignedRoute(
            'client-files.show',
            now()->addDays(self::LINK_DAYS),
            ['file' => $file->getKey()],
        );

        $template = (string) MessageTemplate::query()->where('key', 'file_ready')->value('body');

        $count = $this->sms->handle(
            sender: $trainer,
            client: $file->client,
            text: $this->renderer->render($template, $this->renderer->contextFor($file->client, extra: [
                'linkPliku' => $link,
            ])),
            subject: 'plan do pobrania',
            actor: $trainer,
        );

        $this->log->record($trainer, 'Wysłał plik', $file->client->name.' · '.$file->name);

        return $count;
    }
}
