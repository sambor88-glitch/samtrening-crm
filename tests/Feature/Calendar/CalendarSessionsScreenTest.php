<?php

use App\Domain\Calendar\CalendarAccessToken;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\CalendarSessions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-18 12:00', 'Europe/Warsaw'));

    config()->set('calendar.client_id', 'id');
    config()->set('calendar.client_secret', 'sekret');
    config()->set('calendar.refresh_token', 'refresh');
    config()->set('calendar.cache_seconds', 0);

    Cache::put(CalendarAccessToken::CACHE_KEY, 'dostep', 3600);

    $this->trainer = User::factory()->create();
    $this->anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz', 'rate' => 12000]);
});

test('a training from the diary is offered, ticked, at the card rate', function () {
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertSee('Z kalendarza')
        ->assertSee('Anna Motkowicz')
        ->assertSee('17.09.2026')
        ->assertCount('selected', 1)
        ->assertSet('prices.'.md5('Anna trening2026-09-17T10:00:00+02:00'), '120');
});

test('ticked trainings are logged, and leave the list', function () {
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->call('log')
        ->assertDispatched('session-logged')
        ->assertCount('selected', 0);

    $session = TrainingSession::query()->sole();

    expect($session->client_id)->toBe($this->anna->getKey())
        ->and($session->date->toDateString())->toBe('2026-09-17')
        ->and($session->price)->toBe(12000)
        ->and($session->kind)->toBe(SessionKind::Completed);
});

test('unticking means not logged', function () {
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->set('selected', [])
        ->call('log')
        ->assertDispatched('toast', variant: 'error');

    expect(TrainingSession::query()->count())->toBe(0);
});

test('an edited price wins over the card rate', function () {
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);
    $key = md5('Anna trening2026-09-17T10:00:00+02:00');

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->set('prices.'.$key, '90')
        ->call('log');

    expect(TrainingSession::query()->sole()->price)->toBe(9000);
});

test('an unreadable amount falls back to the rate rather than logging a zero', function () {
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);
    $key = md5('Anna trening2026-09-17T10:00:00+02:00');

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->set('prices.'.$key, 'dużo')
        ->call('log');

    expect(TrainingSession::query()->sole()->price)->toBe(12000);
});

test('a key that was never offered cannot be logged by sending it back', function () {
    // The keys come from a browser; a swapped one has to bounce off the query that
    // built the list, not off the screen (docs/START-TUTAJ.md §7).
    $other = Client::factory()->for(User::factory(), 'trainer')->create(['name' => 'Jakub Żurek']);
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00', 'Jakub Żurek - Trening' => '2026-09-17T12:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->set('selected', [md5('Jakub Żurek - Trening2026-09-17T12:00:00+02:00')])
        ->call('log');

    expect(TrainingSession::query()->where('client_id', $other->getKey())->count())->toBe(0);
});

test('an unrecognised entry is shown apart, never logged', function () {
    diary(['Przegląd sprzętu' => '2026-09-17T10:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertSee('Nierozpoznane')
        ->assertSee('Przegląd sprzętu')
        ->assertCount('selected', 0);
});

test('an ambiguous title names the cards it could mean', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Kowalska']);
    diary(['Anna Motkowicz i Anna Kowalska' => '2026-09-17T10:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertSee('Nierozpoznane')
        ->assertSee('pasuje do')
        ->assertSee('Anna Kowalska');
});

test('a studio with no calendar is told how to connect one', function () {
    config()->set('calendar.refresh_token', null);
    Http::fake();

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertSee('Kalendarz niepodłączony');

    Http::assertNothingSent();
});

test('a diary that refuses does not take the sessions screen down', function () {
    Http::fake(['www.googleapis.com/calendar/*' => Http::response(['error' => 'backendError'], 500)]);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertOk()
        ->assertSee('Nie udało się odczytać kalendarza');
});

test('nothing to log says so, rather than showing an empty table', function () {
    diary([]);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertSee('Nic do wbicia');
});

test('a session logged elsewhere stops being offered', function () {
    TrainingSession::factory()->for($this->anna)->on('2026-09-17')->create();
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    Livewire::actingAs($this->trainer)->test(CalendarSessions::class)
        ->assertSee('Nic do wbicia');
});
