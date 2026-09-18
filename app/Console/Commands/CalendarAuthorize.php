<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * One-off: obtains the refresh token that lets the CRM read the studio's diary — SC-65.
 *
 * Deliberately a second consent, separate from `gmail:authorize`. One token covering
 * both would mean a withdrawn calendar consent also stops the reminders going out,
 * and docs/START-TUTAJ.md §9 does not allow one channel to take another down.
 *
 * Runs in two passes and never prompts, because Forge's command runner has no
 * interactive terminal. The code is exchanged server side, so the client secret never
 * travels through a browser address bar or a command log.
 */
class CalendarAuthorize extends Command
{
    protected $signature = 'calendar:authorize {--code= : Kod z paska adresu, z drugiego przebiegu}';

    protected $description = 'Pobiera token odświeżania do odczytu kalendarza Google.';

    public function handle(): int
    {
        $config = config('calendar');

        if (blank($config['client_id'] ?? null) || blank($config['client_secret'] ?? null)) {
            $this->error('Brak danych klienta OAuth.');
            $this->line('Ustaw GOOGLE_CALENDAR_CLIENT_ID i GOOGLE_CALENDAR_CLIENT_SECRET,');
            $this->line('albo zostaw puste — wtedy wezmą się te same co GMAIL_CLIENT_ID i GMAIL_CLIENT_SECRET.');

            return self::FAILURE;
        }

        $code = trim((string) $this->option('code'));

        return $code === ''
            ? $this->showConsentUrl($config)
            : $this->exchange($config, $code);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function showConsentUrl(array $config): int
    {
        $url = $config['endpoints']['auth'].'?'.http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => $config['scope'],
            'access_type' => 'offline',
            // Without this Google skips the refresh token on a repeat consent, and the
            // account has already granted one for Gmail.
            'prompt' => 'consent',
            'login_hint' => $config['account'],
        ]);

        $this->newLine();
        $this->line('1. Otwórz ten adres w przeglądarce zalogowanej na '.($config['account'] ?: 'koncie ze studia').':');
        $this->newLine();
        $this->line($url);
        $this->newLine();
        $this->line('2. Zatwierdź zgodę — prosimy wyłącznie o <options=bold>odczyt</> kalendarza.');
        $this->line('   Przeglądarka spróbuje wejść na '.$config['redirect_uri'].' i pokaże błąd');
        $this->line('   połączenia — to normalne. Z paska adresu skopiuj wartość parametru code=.');
        $this->newLine();
        $this->line('3. Uruchom ponownie, podając ten kod:');
        $this->newLine();
        $this->line('   php artisan calendar:authorize --code=TUTAJ_KOD');
        $this->newLine();
        $this->comment('Kod jest jednorazowy i ważny kilka minut — nie odkładaj drugiego przebiegu na później.');
        $this->newLine();
        $this->warn('Wysyłka poczty działa dalej bez zmian — to osobna zgoda i osobny token.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function exchange(array $config, string $code): int
    {
        // A code copied out of the address bar is often percent-encoded.
        if (str_contains($code, '%')) {
            $code = urldecode($code);
        }

        $response = Http::asForm()
            ->timeout((int) ($config['timeout'] ?? 15))
            ->post($config['endpoints']['token'], [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri'],
            ]);

        if ($response->failed()) {
            $this->error('Google odrzucił kod: '.$response->body());
            $this->line('Kody są jednorazowe i ważne kilka minut — jeśli minęło więcej, zacznij od nowa');
            $this->line('bez parametru --code.');

            return self::FAILURE;
        }

        $refreshToken = $response->json('refresh_token');

        if (! is_string($refreshToken) || $refreshToken === '') {
            $this->error('Google nie zwrócił tokenu odświeżania.');
            $this->line('Dzieje się tak, gdy zgoda została już raz udzielona. Cofnij dostęp aplikacji');
            $this->line('na https://myaccount.google.com/permissions i powtórz.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Gotowe. Wpisz w Forge → Settings → Environment:');
        $this->newLine();
        $this->line('GOOGLE_CALENDAR_REFRESH_TOKEN='.$refreshToken);
        $this->newLine();
        $this->line('Potem php artisan config:cache.');
        $this->newLine();
        $this->comment('GMAIL_REFRESH_TOKEN zostaw bez zmian — poczta chodzi na swoim.');

        return self::SUCCESS;
    }
}
