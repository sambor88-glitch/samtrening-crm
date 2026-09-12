<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Balance;
use App\Domain\Billing\Earnings;
use App\Domain\Billing\Export\SessionCsvExport;
use App\Domain\Clients\Actions\AttachClientFile;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Actions\BlockTrainer;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Dialogs\LogSessionDialog;
use App\Livewire\Trainer\ClientCard;
use App\Livewire\Trainer\Dashboard;
use App\Livewire\Trainer\Payments;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * docs/START-TUTAJ.md §14 — the definition of readiness, one test per line, in its order.
 *
 * The point is not to duplicate the suite: each of these passes through the real screens the way
 * a person would, so the list can be answered with a run rather than with an opinion. Four lines
 * cannot be answered here at all (a Polish Excel, a production backup); they say so and name what
 * has to happen on the server.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));
    $this->seed(MessageTemplateSeeder::class);

    Setting::query()->create([
        'reminders_enabled' => true,
        'reminder_threshold_days' => 14,
        'free_cancellation_hours' => 24,
        'retention_months' => 60,
        'ticker_enabled' => true,
    ]);

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski', 'blik_number' => '600 100 200']);
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 200 300']);
    $this->bartek = User::factory()->create(['name' => 'Bartek Nowak', 'blik_number' => '600 400 500']);
});

test('§14 · a trainer logs in, logs a session in two taps from the week closer and sees the right balance', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Magdalena Wróbel', 'rate' => 20000]);

    $this->post(route('login'), ['email' => $this->kasia->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    // Tap one: the button in the untouched row carries the client to the dialog.
    $dashboard = Livewire::actingAs($this->kasia)->test(Dashboard::class);
    expect($dashboard->html())->toContain("\$dispatch('log-session', { client: ".$client->getKey().' }');

    // Tap two: the dialog opens with the client, today's date and their rate already in place.
    Livewire::actingAs($this->kasia)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $client->getKey())
        ->assertSet('clientId', $client->getKey())
        ->assertSet('date', '2026-09-15')
        ->assertSet('price', '200')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(Balance::class)->forClient($client->fresh()))->toBe(20000);

    Livewire::actingAs($this->kasia)->test(Dashboard::class)->assertSee('200 zł');
});

test('§14 · the balance comes from Billing\Balance, across every kind of session', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->on('2026-09-01')->create(['price' => 20000]);          // na saldzie
    TrainingSession::factory()->for($client)->on('2026-09-02')->paid()->create(['price' => 20000]);  // zapłacona
    TrainingSession::factory()->for($client)->on('2026-09-03')->cancelled()->create(['price' => 20000]); // odwołanie naliczone
    TrainingSession::factory()->for($client)->on('2026-09-04')->waived()->create();                  // odwołanie darmowe
    TrainingSession::factory()->for($client)->on('2026-09-05')->noShow()->create(['price' => 20000]); // nieobecność
    TrainingSession::factory()->for($client)->on('2026-09-06')->create(['price' => 15000]);          // nadpisana kwota

    expect(app(Balance::class)->forClient($client))->toBe(75000);

    // An archived client keeps their debt and their history.
    $client->update(['archived' => true]);
    expect(app(Balance::class)->forClient($client->fresh()))->toBe(75000)
        ->and($client->sessions()->count())->toBe(6);
});

test('§14 · Kasia does not see Bartek\'s clients, however she asks', function () {
    $his = Client::factory()->for($this->bartek, 'trainer')->create(['name' => 'Aleksander Górski']);

    // By URL.
    $this->actingAs($this->kasia)->get(route('clients.show', $his))->assertForbidden();

    // By component.
    Livewire::actingAs($this->kasia)->test(ClientCard::class, ['client' => $his])->assertForbidden();

    // And by the screens that list anybody.
    $this->actingAs($this->kasia)->get(route('clients.index'))->assertDontSee('Aleksander Górski');
    $this->actingAs($this->kasia)->get(route('admin.clients.index'))->assertForbidden();
});

test('§14 · the owner switches to the admin panel, sees the month and the year, and cannot be blocked', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create();
    TrainingSession::factory()->for($client)->on('2026-09-09')->create(['price' => 20000]);
    TrainingSession::factory()->for($client)->on('2026-03-09')->create(['price' => 18000]);

    $this->actingAs($this->owner)->get(route('dashboard'))->assertSee('Admin');

    $earnings = app(Earnings::class);

    expect($earnings->forStudio(DateRange::fromPrefix('2026-09'))->revenue)->toBe(20000)
        ->and($earnings->forStudio(DateRange::fromPrefix('2026'))->revenue)->toBe(38000);

    $this->actingAs($this->owner)->get(route('admin.dashboard'))->assertOk();

    expect(fn () => app(BlockTrainer::class)->handle($this->owner, $this->owner, true))
        ->toThrow(RuntimeException::class);
});

test('§14 · a session survives a messaging failure, because they are two operations', function () {
    Queue::fake();

    $noPhone = Client::factory()->for($this->kasia, 'trainer')->create(['phone' => null, 'rate' => 20000]);

    Livewire::actingAs($this->kasia)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $noPhone->getKey())
        ->call('save')
        ->assertHasNoErrors();

    expect($noPhone->sessions()->count())->toBe(1);

    // The message cannot go out, and says so — the session stays where it is.
    Livewire::actingAs($this->kasia)->test(Payments::class)
        ->call('requestBlik', $noPhone->getKey())
        ->assertDispatched('toast', variant: 'error');

    expect($noPhone->sessions()->count())->toBe(1)
        ->and($noPhone->sessions()->sole()->payment_status)->toBe(PaymentStatus::Balance);
});

test('§14 · a trainer with no BLIK number is stopped before sending and told where to fix it', function () {
    Queue::fake();

    $fresh = User::factory()->create(['blik_number' => null]);
    $client = Client::factory()->for($fresh, 'trainer')->create(['phone' => '+48 600 300 400']);
    TrainingSession::factory()->for($client)->on('2026-09-02')->create(['price' => 20000]);

    Livewire::actingAs($fresh)->test(Payments::class)
        ->call('requestBlik', $client->getKey())
        ->assertDispatched('toast', variant: 'error');

    Queue::assertNothingPushed();

    // And the place to fix it is one screen away, with the field waiting.
    $this->actingAs($fresh)->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Twój numer BLIK')
        // The sentence wraps in the view, so the halves are asserted separately.
        ->assertSee('Podstawia się w prośbie o płatność, monicie i podsumowaniu miesiąca.')
        ->assertSee('żadna z tych wiadomości nie wyjdzie.');
});

test('§14 · everything that changes state leaves an entry with an author and context', function () {
    // The full §7 list has its own walk — tests/Feature/Audit/ActivityLogCompletenessTest.php.
    // Here: the guarantee that nothing writes without saying who and what.
    $client = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Magdalena Wróbel']);

    Livewire::actingAs($this->kasia)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $client->getKey())
        ->call('save');

    $entries = ActivityEntry::query()->get();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry->actor_name)->not->toBeEmpty()
            ->and($entry->context)->not->toBeEmpty()
            ->and($entry->happened_at)->not->toBeNull();
    }
});

test('§14 · the CSV carries a BOM, semicolons and no session notes', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Zofia Łąkowska']);
    TrainingSession::factory()->for($client)->on('2026-09-09')->create([
        'price' => 20000,
        'notes' => 'Ból w odcinku lędźwiowym',
    ]);

    $file = app(SessionCsvExport::class)->forTrainer($this->kasia, DateRange::fromPrefix('2026-09'));

    expect($file->contents)
        ->toStartWith("\u{FEFF}")                 // bez BOM-u polski Excel łamie diakrytyki
        ->toContain('Data;Klient')
        ->toContain('Zofia Łąkowska')             // diakrytyki w danych
        ->toContain("\r\n")
        ->not->toContain('Ból w odcinku lędźwiowym'); // minimalizacja RODO
})->note('Sam Excel to ostatni krok — plik otwiera człowiek, bajty sprawdza ten test.');

test('§14 · an empty roster reads as an invitation, not as a failure', function () {
    $fresh = User::factory()->create(['name' => 'Anna Zielińska']);

    $this->actingAs($fresh)->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Kartoteka jest pusta')
        ->assertSee('Dodaj pierwszego klienta — zajmie to dwadzieścia sekund.')
        ->assertDontSee('Brak danych')
        ->assertDontSee('Błąd');

    $this->actingAs($fresh)->get(route('dashboard'))
        ->assertSee('Wszystko rozliczone.')
        ->assertSee('Zero zaległości.');
});

test('§14 · health data is encrypted at rest and files never touch the public disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $client = Client::factory()->for($this->kasia, 'trainer')->create([
        'contraindications' => 'Rekonstrukcja ACL, bez skoków',
    ]);
    TrainingSession::factory()->for($client)->on('2026-09-09')->create(['notes' => 'Kolano bolało']);

    expect(DB::table('clients')->where('id', $client->getKey())->value('contraindications'))
        ->not->toContain('ACL')
        ->and(DB::table('training_sessions')->where('client_id', $client->getKey())->value('notes'))
        ->not->toContain('Kolano');

    $file = UploadedFile::fake()->create('plan.pdf', 10);
    app(AttachClientFile::class)->handle($this->kasia, $client, $file);

    expect($client->files()->sole()->path)->toStartWith('klienci/');

    Storage::disk('local')->assertExists($client->files()->sole()->path);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('§14 · the scheduled work is registered: reminders daily, retention monthly', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command.' @ '.$event->expression)
        ->implode("\n");

    expect($scheduled)->toContain('samtrening:monity')
        ->toContain('0 10 * * *')
        ->toContain('samtrening:retencja');
});

test('§14 · the queue carries the sending, so a slow provider never blocks a save', function () {
    Queue::fake();

    $client = Client::factory()->for($this->kasia, 'trainer')->create(['phone' => '+48 600 300 400']);
    TrainingSession::factory()->for($client)->on('2026-09-02')->create(['price' => 20000]);

    Livewire::actingAs($this->kasia)->test(Payments::class)
        ->call('requestBlik', $client->getKey());

    Queue::assertPushed(SendSmsMessage::class);
});
