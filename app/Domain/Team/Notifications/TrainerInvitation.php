<?php

namespace App\Domain\Team\Notifications;

use App\Domain\Team\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The mail a new trainer gets. The link lives seven days, and until it is used the account sees
 * no client data at all — which the message says out loud, because people ask.
 */
class TrainerInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $invitedBy,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $link = route('activation.create', ['token' => $this->token]).'?email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Zaproszenie do zespołu SAMtrening')
            ->greeting('Cześć '.Str::before($notifiable->name, ' ').',')
            ->line($this->invitedBy.' zaprasza Cię do CRM-a studia SAMtrening.')
            ->action('Ustaw hasło i aktywuj konto', $link)
            ->line('Link jest ważny 7 dni. Do czasu aktywacji konto nie widzi żadnych danych klientów.')
            ->line('Po zalogowaniu zobaczysz wyłącznie swoich klientów — Twoje notatki zostają Twoje.')
            ->salutation('SAMtrening');
    }
}
