<?php

namespace App\Domain\Team\Notifications;

use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Illuminate\Notifications\Notification;

/**
 * The owner's nudge about an unpaid client. It waits inside the CRM rather than in a mailbox or
 * on a phone — the studio decided a pop-up on the trainer's next visit is enough, and a push
 * would have meant building a PWA nobody asked for (SC-39).
 */
class TrainerReminder extends Notification
{
    public function __construct(
        private readonly Client $client,
        private readonly int $amount,
        private readonly string $from,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|int>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'client_id' => $this->client->getKey(),
            'client' => $this->client->name,
            'amount' => Money::format($this->amount),
            'from' => $this->from,
            'message' => $this->client->name.' ma nierozliczone '.Money::format($this->amount).'.',
        ];
    }
}
