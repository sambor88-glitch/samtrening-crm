<?php

namespace App\Domain\Team\Notifications;

use App\Support\Plural;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Laravel's reset mail, said in Polish. The token, the link and the single-use rule stay
 * the framework's — only the words are ours.
 */
class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Ustaw nowe hasło w '.config('app.name'))
            ->greeting('Cześć,')
            ->line('Ktoś — mamy nadzieję, że Ty — poprosił o ustawienie nowego hasła do konta w SAMtrening CRM.')
            ->action('Ustaw nowe hasło', $url)
            ->line('Link jest ważny '.Plural::of($minutes, 'minutę', 'minuty', 'minut').' i działa jednorazowo.')
            ->line('Jeśli to nie Ty, zignoruj tę wiadomość — hasło zostaje bez zmian.')
            ->salutation('SAMtrening');
    }
}
