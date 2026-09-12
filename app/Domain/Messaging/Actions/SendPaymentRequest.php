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
 * The "please pay" text — the trainer's own BLIK number, not a payment link, because that is how
 * this studio gets paid (docs/START-TUTAJ.md §9).
 */
class SendPaymentRequest
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly SendSms $sms,
        private readonly ActivityLogger $log,
        private readonly Balance $balance,
    ) {}

    public function handle(User $trainer, Client $client): SmsCount
    {
        $template = (string) MessageTemplate::query()->where('key', 'payment_request')->value('body');

        $count = $this->sms->handle(
            sender: $trainer,
            client: $client,
            text: $this->renderer->render($template, $this->renderer->contextFor($client)),
            subject: 'prośba o BLIK',
            actor: $trainer,
        );

        $this->log->record(
            $trainer,
            'Poprosił o BLIK',
            $client->name.' · '.Money::format($this->balance->forClient($client)),
        );

        return $count;
    }
}
