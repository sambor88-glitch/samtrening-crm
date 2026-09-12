<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Export\SessionCsvExport;
use App\Domain\Clients\Actions\ArchiveClient;
use App\Domain\Clients\Actions\CreateClient;
use App\Domain\Clients\Actions\SetClientRate;
use App\Domain\Clients\Actions\UpdateClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendPaymentRequest;
use App\Domain\Messaging\Actions\SendReminder;
use App\Domain\Settings\Actions\UpdateStudioRules;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Actions\ActivateAccount;
use App\Domain\Team\Actions\BlockTrainer;
use App\Domain\Team\Actions\InviteTrainer;
use App\Domain\Team\Actions\ResetTrainerPassword;
use App\Domain\Team\Models\User;
use App\Domain\Training\Actions\DeleteSession;
use App\Domain\Training\Actions\LogSession;
use App\Domain\Training\Actions\RestoreSession;
use App\Domain\Training\Actions\UpdateSessionPrice;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * docs/START-TUTAJ.md §7, verbatim and in the order it lists them. This is the whole point of
 * SC-40: a rule written in prose is a wish, a rule written as a list a test walks is a rule.
 */
const OBOWIAZKOWE = [
    'wbicie sesji',
    'edycja kwoty',
    'usunięcie sesji',
    'cofnięcie usunięcia sesji',
    'zmiana stawki',
    'dodanie klienta',
    'edycja klienta',
    'wysłanie prośby o płatność',
    'monit',
    'odznaczenie gotówki',
    'archiwizacja',
    'usunięcie danych RODO',
    'zaproszenie trenera',
    'blokada trenera',
    'zmiana ustawień',
    'reset hasła',
    'aktywacja konta',
    'eksport CSV',
];

beforeEach(function () {
    Queue::fake();
    Notification::fake();

    $this->seed(MessageTemplateSeeder::class);

    Setting::query()->create(['reminders_enabled' => true, 'reminder_threshold_days' => 14]);

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->trainer = User::factory()->create([
        'name' => 'Katarzyna Samborska',
        'blik_number' => '600 100 200',
    ]);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
        'rate' => 20000,
    ]);
});

test('każde obowiązkowe zdarzenie z §7 zostawia wpis z autorem i kontekstem', function () {
    $session = TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);
    $deleted = TrainingSession::factory()->for($this->client)->on('2026-09-10')->create(['price' => 20000]);

    // The trigger for each event, keyed by its name in §7. Everything runs as a real action —
    // the log has to survive being called from a job or a command, not just from a screen.
    $events = [
        'wbicie sesji' => fn () => app(LogSession::class)->handle($this->trainer, $this->client, [
            'date' => '2026-09-12',
            'service' => 'Trening personalny 1:1',
            'price' => 20000,
            'kind' => SessionKind::Completed,
            'payment_status' => PaymentStatus::Balance,
        ]),
        'edycja kwoty' => fn () => app(UpdateSessionPrice::class)->handle($this->trainer, $session, 18000),
        'usunięcie sesji' => fn () => app(DeleteSession::class)->handle($this->trainer, $deleted),
        'cofnięcie usunięcia sesji' => fn () => app(RestoreSession::class)->handle($this->trainer, $deleted),
        'zmiana stawki' => fn () => app(SetClientRate::class)->handle($this->trainer, $this->client, 22000),
        'dodanie klienta' => fn () => app(CreateClient::class)->handle($this->trainer, [
            'name' => 'Ewa Lisowska',
            'phone' => '+48 600 500 600',
            'rate' => 20000,
            'consent_given' => true,
        ]),
        'edycja klienta' => fn () => app(UpdateClient::class)->handle($this->trainer, $this->client, [
            'goal' => 'Powrót do biegania po kontuzji',
        ]),
        'wysłanie prośby o płatność' => fn () => app(SendPaymentRequest::class)->handle($this->trainer, $this->client),
        'monit' => fn () => app(SendReminder::class)->handle($this->trainer, $this->client),
        'odznaczenie gotówki' => fn () => app(MarkAsPaid::class)->handle($this->trainer, $this->client),
        'zaproszenie trenera' => fn () => app(InviteTrainer::class)->handle($this->owner, [
            'name' => 'Piotr Nowak',
            'email' => 'piotr@samtrening.com',
        ]),
        'blokada trenera' => fn () => app(BlockTrainer::class)->handle($this->owner, $this->trainer, true),
        'reset hasła' => fn () => app(ResetTrainerPassword::class)->handle($this->owner, $this->trainer),
        'aktywacja konta' => fn () => app(ActivateAccount::class)->handle(
            User::factory()->invited()->create(['name' => 'Anna Zielińska']),
            'pierwsze-haslo-anny',
        ),
        'eksport CSV' => fn () => app(SessionCsvExport::class)
            ->forStudio($this->owner, DateRange::fromPrefix('2026-09')),
        'zmiana ustawień' => fn () => app(UpdateStudioRules::class)
            ->handle($this->owner, ['reminder_threshold_days' => 7]),
        'archiwizacja' => fn () => app(ArchiveClient::class)->handle(
            $this->trainer,
            Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Zofia Testowa']),
        ),
    ];

    // What has no action to trigger it yet. Naming the story here — instead of quietly leaving
    // these off the list — is what makes the omission visible. "Zmiana ustawien" moved up into
    // the list above when SC-44 landed, which is the move this shape was built for.
    $czekaja = [
        'usunięcie danych RODO' => 'SC-46',
    ];

    expect([...array_keys($events), ...array_keys($czekaja)])
        ->toEqualCanonicalizing(OBOWIAZKOWE);

    $braki = [];
    $zapisane = [];

    foreach ($events as $nazwa => $wywolaj) {
        $before = (int) (ActivityEntry::query()->max('id') ?? 0);

        $wywolaj();

        $entry = ActivityEntry::query()->where('id', '>', $before)->orderBy('id')->first();

        if ($entry === null) {
            $braki[] = $nazwa.' — brak wpisu w logu';

            continue;
        }

        if (blank($entry->actor_name)) {
            $braki[] = $nazwa.' — wpis bez autora';
        }

        if (blank($entry->context)) {
            $braki[] = $nazwa.' — wpis bez kontekstu';
        }

        $zapisane[$nazwa] = $entry->action.' · '.$entry->context;
    }

    expect($braki)->toBe([])
        ->and($zapisane)->toHaveCount(count($events))
        // Spot checks, so a log full of "Zmieniono coś" cannot pass the count above.
        ->and($zapisane['wbicie sesji'])->toBe('Wbił sesję · Magdalena Wróbel · 200 zł · na saldo')
        ->and($zapisane['edycja kwoty'])->toBe('Zmienił kwotę sesji · Magdalena Wróbel · 09.09.2026 · 200 zł → 180 zł')
        ->and($zapisane['zmiana stawki'])->toBe('Zmienił stawkę · Magdalena Wróbel · 200 zł → 220 zł')
        ->and($zapisane['blokada trenera'])->toStartWith('Zablokował trenera · Katarzyna Samborska')
        ->and($zapisane['eksport CSV'])->toStartWith('Wyeksportował CSV · Wrzesień 2026');
});

test('każdy wpis ma czas i autora, który zostaje po skasowaniu konta', function () {
    $leaving = User::factory()->create(['name' => 'Katarzyna Samborska']);

    app(SetClientRate::class)->handle($leaving, $this->client, 22000);

    $entry = ActivityEntry::query()->sole();

    expect($entry->happened_at)->not->toBeNull()
        ->and($entry->user_id)->toBe($leaving->id);

    $leaving->delete();

    expect($entry->fresh())->actor_name->toBe('Katarzyna Samborska')->user_id->toBeNull();
});
