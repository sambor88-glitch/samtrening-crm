<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Earnings;
use App\Domain\Clients\Models\Client;
use App\Domain\Privacy\Actions\AnonymizeClient;
use App\Domain\Privacy\Actions\SweepRetention;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Dialogs\DeleteDataDialog;
use App\Livewire\Trainer\ClientCard;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    Setting::query()->create(['reminders_enabled' => true, 'retention_months' => 60]);

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
        'email' => 'magda@example.com',
        'goal' => 'Powrót do biegania po kontuzji',
        'baseline' => 'Bieg 5 km w 32 minuty',
        'contraindications' => 'Przebyta rekonstrukcja ACL',
        'trainer_notes' => 'Lepiej reaguje na krótsze serie',
        'next_session_plan' => 'Martwy ciąg, ale lżej',
        'guardian' => 'Anna Wróbel',
        'company_name' => 'Wróbel sp. z o.o.',
        'tax_id' => '1234567890',
    ]);
});

test('everything personal goes and the money stays', function () {
    $this->client->tags()->create(['label' => 'Zdrowa ciąża', 'variant' => 'accent']);
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create([
        'price' => 20000,
        'notes' => 'Kolano bolało po trzeciej serii',
    ]);

    $before = app(Earnings::class)->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->revenue;

    app(AnonymizeClient::class)->handle($this->trainer, $this->client);

    $client = $this->client->fresh();

    expect($client->name)->toBe('Dane usunięte #'.str_pad((string) $client->getKey(), 4, '0', STR_PAD_LEFT))
        ->and($client->phone)->toBeNull()
        ->and($client->email)->toBeNull()
        ->and($client->goal)->toBeNull()
        ->and($client->baseline)->toBeNull()
        ->and($client->contraindications)->toBeNull()
        ->and($client->trainer_notes)->toBeNull()
        ->and($client->next_session_plan)->toBeNull()
        ->and($client->guardian)->toBeNull()
        ->and($client->company_name)->toBeNull()
        ->and($client->tax_id)->toBeNull()
        ->and($client->archived)->toBeTrue()
        ->and($client->tags()->count())->toBe(0);

    // The sessions are still there, with the amounts intact and the notes gone.
    $session = $client->sessions()->sole();

    expect($session->price)->toBe(20000)
        ->and($session->notes)->toBeNull()
        ->and(app(Earnings::class)->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->revenue)
        ->toBe($before);
});

test('nothing personal is left anywhere in the row', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['notes' => 'Kolano']);

    app(AnonymizeClient::class)->handle($this->trainer, $this->client);

    // Read straight from the database: the encrypted column would hide behind the cast.
    $row = (array) DB::table('clients')->where('id', $this->client->getKey())->first();

    foreach (['Magdalena', 'Wróbel', '600 300 400', 'magda@example.com', 'ACL', 'Anna'] as $personal) {
        expect(implode(' ', array_map(fn ($value) => (string) $value, $row)))->not->toContain($personal);
    }

    expect(DB::table('training_sessions')->where('client_id', $this->client->getKey())->value('notes'))->toBeNull();
});

test('notes on a deleted session go too', function () {
    $session = TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['notes' => 'Kolano']);
    $session->delete();

    app(AnonymizeClient::class)->handle($this->trainer, $this->client);

    expect(DB::table('training_sessions')->where('id', $session->getKey())->value('notes'))->toBeNull();
});

test('the files leave the disk, not just the table', function () {
    Storage::fake('local');

    $path = UploadedFile::fake()->create('plan.pdf', 12)->store('klienci/'.$this->client->getKey(), 'local');
    $this->client->files()->create(['name' => 'plan.pdf', 'extension' => 'PDF', 'path' => $path, 'size' => 12]);

    Storage::disk('local')->assertExists($path);

    app(AnonymizeClient::class)->handle($this->trainer, $this->client);

    Storage::disk('local')->assertMissing($path);
    expect($this->client->files()->count())->toBe(0);
});

test('the log keeps the name, because it has to say whose data went', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->count(3)->create();

    app(AnonymizeClient::class)->handle($this->trainer, $this->client);

    expect(ActivityEntry::query()->where('action', 'Usunął dane klienta (RODO)')->first())
        ->context->toContain('Magdalena Wróbel → Dane usunięte #')
        ->context->toContain('3 sesje zostaje bez notatek')
        ->actor_name->toBe('Katarzyna Samborska');
});

test('the dialog says what goes, what stays and that it cannot be undone', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(DeleteDataDialog::class)
        ->dispatch('delete-client-data', client: $this->client->id)
        ->assertSee('Żądanie usunięcia danych')
        ->assertSee('Magdalena Wróbel')
        ->assertSee('kontuzje i przeciwwskazania')
        ->assertSee('daty i kwoty sesji')
        ->assertSee('Tego nie da się cofnąć.')
        // An unpaid balance gets its own warning.
        ->assertSee('nie wyślesz już prośby o płatność');
});

test('the dialog does the deletion and the card comes back anonymous', function () {
    Livewire::actingAs($this->trainer)->test(DeleteDataDialog::class)
        ->dispatch('delete-client-data', client: $this->client->id)
        ->call('confirm')
        ->assertDispatched('client-data-deleted')
        ->assertDispatched('toast', message: 'Dane Magdalena Wróbel usunięte. Została historia sesji bez notatek.')
        ->assertSet('open', false);

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client->fresh()])
        ->assertDontSee('Magdalena Wróbel')
        ->assertSee('Dane usunięte #');
});

test('one trainer cannot wipe another trainer client', function () {
    $theirs = Client::factory()->for(User::factory(), 'trainer')->create();

    Livewire::actingAs($this->trainer)->test(DeleteDataDialog::class)
        ->dispatch('delete-client-data', client: $theirs->id)
        ->assertForbidden();

    expect($theirs->fresh()->name)->toBe($theirs->name);
});

test('the sweep anonymises archived cards past the retention period', function () {
    $old = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Dawny Klient', 'archived' => true]);
    TrainingSession::factory()->for($old)->on('2021-01-10')->create(); // ponad 60 miesięcy

    $recent = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Niedawny Klient', 'archived' => true]);
    TrainingSession::factory()->for($recent)->on('2024-01-10')->create();

    expect(app(SweepRetention::class)->handle())->toBe(1)
        ->and($old->fresh()->name)->toStartWith('Dane usunięte #')
        ->and($recent->fresh()->name)->toBe('Niedawny Klient')
        // Nobody pressed anything, so the log says System.
        ->and(ActivityEntry::query()->where('action', 'Usunął dane klienta (RODO)')->first()->actor_name)
        ->toBe('System');

    expect(ActivityEntry::query()->where('action', 'Wyczyścił kartoteki po retencji')->first())
        ->context->toBe('1 kartoteka · retencja 60 mies.');
});

test('the sweep leaves active clients alone, however old their last session', function () {
    $active = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Wraca Po Latach']);
    TrainingSession::factory()->for($active)->on('2019-01-10')->create();

    expect(app(SweepRetention::class)->handle())->toBe(0)
        ->and($active->fresh()->name)->toBe('Wraca Po Latach');
});

test('the sweep does not anonymise the same card twice', function () {
    $old = Client::factory()->for($this->trainer, 'trainer')->create(['archived' => true]);
    TrainingSession::factory()->for($old)->on('2020-05-10')->create();

    expect(app(SweepRetention::class)->handle())->toBe(1)
        ->and(app(SweepRetention::class)->handle())->toBe(0);
});

test('the sweep follows the studio retention setting', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create(['archived' => true]);
    TrainingSession::factory()->for($client)->on('2024-01-10')->create();

    expect(app(SweepRetention::class)->handle())->toBe(0);

    Setting::current()->update(['retention_months' => 12]);

    expect(app(SweepRetention::class)->handle())->toBe(1);
});

test('the command runs the sweep and says what it did', function () {
    $old = Client::factory()->for($this->trainer, 'trainer')->create(['archived' => true]);
    TrainingSession::factory()->for($old)->on('2019-05-10')->create();

    $this->artisan('samtrening:retencja')
        ->expectsOutputToContain('Zanonimizowano 1 kartotekę.')
        ->assertSuccessful();

    $this->artisan('samtrening:retencja')
        ->expectsOutputToContain('Nic do wyczyszczenia')
        ->assertSuccessful();
});
