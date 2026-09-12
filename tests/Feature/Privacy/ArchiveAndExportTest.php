<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Earnings;
use App\Domain\Clients\Actions\ArchiveClient;
use App\Domain\Clients\Models\Client;
use App\Domain\Privacy\Actions\ExportClientData;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\ClientCard;
use App\Livewire\Trainer\ClientList;
use App\Livewire\Trainer\Dashboard;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
        'email' => 'magda@example.com',
        'rate' => 20000,
        'goal' => 'Powrót do biegania po kontuzji',
        'contraindications' => 'Przebyta rekonstrukcja ACL, bez skoków',
        'trainer_notes' => 'Lepiej reaguje na krótsze serie',
        'consent_given' => true,
        'consent_date' => '2026-07-01',
    ]);
});

test('archiving is refused while the balance is not settled', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['price' => 20000]);

    // Not only the greyed-out button: the action refuses too.
    expect(fn () => app(ArchiveClient::class)->handle($this->trainer, $this->client))
        ->toThrow(RuntimeException::class, 'Najpierw rozlicz saldo — 200 zł nie może zniknąć z widoku.');

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->assertSee('Najpierw rozlicz saldo')
        ->call('toggleArchive')
        ->assertDispatched('toast', variant: 'error');

    expect($this->client->refresh()->archived)->toBeFalse();
});

test('a settled client goes to the archive and comes back', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->call('toggleArchive')
        ->assertDispatched('toast')
        ->assertSee('Przywróć z archiwum');

    expect($this->client->refresh()->archived)->toBeTrue()
        ->and(ActivityEntry::query()->where('action', 'Zarchiwizował klienta')->first())
        ->context->toBe('Magdalena Wróbel');

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client->fresh()])
        ->call('toggleArchive');

    expect($this->client->refresh()->archived)->toBeFalse()
        ->and(ActivityEntry::query()->where('action', 'Przywrócił klienta z archiwum')->count())->toBe(1);
});

test('an archived client leaves the lists but their sessions stay in the earnings', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create(['price' => 20000]);

    $before = app(Earnings::class)->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->revenue;

    app(ArchiveClient::class)->handle($this->trainer, $this->client);

    expect(app(Earnings::class)->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->revenue)
        ->toBe($before);

    Livewire::actingAs($this->trainer)->test(ClientList::class)->assertDontSee('Magdalena Wróbel');
    Livewire::actingAs($this->trainer)->test(Dashboard::class)->assertViewHas('clients', 0);
});

test('the export carries the whole card, the sessions and the notes', function () {
    $this->client->tags()->create(['label' => 'Zdrowa ciąża', 'variant' => 'accent']);
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create([
        'price' => 20000,
        'service' => 'Trening personalny 1:1',
        'notes' => 'Pierwszy raz bez bólu kolana',
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);

    $file = app(ExportClientData::class)->handle($this->trainer, $this->client);

    expect($file->name)->toBe('samtrening-dane-magdalena-wrobel-2026-09-15.txt')
        ->and($file->contents)
        ->toContain('Imię i nazwisko: Magdalena Wróbel')
        ->toContain('Telefon: +48 600 300 400')
        ->toContain('E-mail: magda@example.com')
        ->toContain('Trener prowadzący: Katarzyna Samborska')
        ->toContain('Stawka: 200 zł za sesję')
        ->toContain('Zgoda na przetwarzanie danych o zdrowiu: tak')
        ->toContain('Data zgody: 01.07.2026')
        ->toContain('Cel: Powrót do biegania po kontuzji')
        ->toContain('Kontuzje i przeciwwskazania: Przebyta rekonstrukcja ACL, bez skoków')
        ->toContain('Notatki: Lepiej reaguje na krótsze serie')
        ->toContain('Tagi: Zdrowa ciąża')
        ->toContain('02.09.2026 · Trening personalny 1:1 · 200 zł · Odbyta · Zapłacone · Pierwszy raz bez bólu kolana')
        ->toContain('Nierozliczone saldo: 200 zł')
        // Read on paper: a blank line between the blocks, and the header stands on its own.
        ->toContain("Wydał: Katarzyna Samborska\n\nKARTA")
        ->toContain("\n\nHISTORIA SESJI");

    expect(ActivityEntry::query()->where('action', 'Wyeksportował dane klienta')->first())
        ->context->toBe('Magdalena Wróbel');
});

test('the export holds nothing about anybody else', function () {
    $other = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Aleksander Górski',
        'phone' => '+48 600 999 888',
    ]);
    TrainingSession::factory()->for($other)->on('2026-09-03')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create();

    $file = app(ExportClientData::class)->handle($this->trainer, $this->client);

    expect($file->contents)
        ->not->toContain('Aleksander Górski')
        ->not->toContain('600 999 888')
        ->not->toContain('03.09.2026');
});

test('empty sections do not appear at all', function () {
    $bare = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Zofia Nowa',
        'goal' => null,
        'baseline' => null,
        'contraindications' => null,
        'trainer_notes' => null,
        'next_session_plan' => null,
    ]);

    $file = app(ExportClientData::class)->handle($this->trainer, $bare);

    expect($file->contents)
        ->toContain('Imię i nazwisko: Zofia Nowa')
        ->not->toContain('CEL I PUNKT STARTOWY')
        ->not->toContain('ZDROWIE')
        ->not->toContain('NOTATKI TRENERA')
        ->not->toContain('HISTORIA SESJI');
});

test('the file comes down from the card under the client name', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->client])
        ->assertSee('Eksport danych klienta')
        ->call('exportData')
        ->assertFileDownloaded('samtrening-dane-magdalena-wrobel-2026-09-15.txt')
        ->assertDispatched('toast', message: 'Dane Magdalena Wróbel pobrane. Przekaż plik tylko tej osobie.');
});

test('one trainer never exports or archives another trainer client', function () {
    $mine = Client::factory()->for(User::factory(), 'trainer')->create();

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $mine])->assertForbidden();
});
