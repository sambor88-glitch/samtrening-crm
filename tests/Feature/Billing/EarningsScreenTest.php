<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Earnings;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->trainer = User::factory()->create();
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
    ]);
});

test('the panel shows this month, its amount and how many sessions were held', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-08-30')->create(['price' => 50000]);

    $this->actingAs($this->trainer)
        ->get(route('earnings.index'))
        ->assertOk()
        ->assertSee('Ile zarobiłeś.')
        ->assertSee('Zarobek — Wrzesień 2026')
        ->assertSee('400 zł')
        ->assertSee('2 odbyte sesje')
        ->assertSee('100% stawki klienta')
        ->assertDontSee('500 zł');
});

test('switching the range moves the heading and the numbers with it', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-08-12')->create(['price' => 50000]);

    Livewire::actingAs($this->trainer)->test(Earnings::class)
        ->assertSee('Zarobek — Wrzesień 2026')
        ->assertSee('200 zł')
        ->call('show', '2026-08')
        ->assertSee('Zarobek — Sierpień 2026')
        ->assertSee('500 zł')
        ->assertDontSee('Zarobek — Wrzesień');
});

test('the year adds its months up', function () {
    TrainingSession::factory()->for($this->client)->on('2026-01-10')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2025-12-31')->create(['price' => 90000]);

    Livewire::actingAs($this->trainer)->test(Earnings::class)
        ->call('show', '2026')
        ->assertSee('Zarobek — Cały 2026')
        ->assertSee('400 zł')
        ->assertDontSee('900 zł');
});

test('a range that makes no sense falls back to this month instead of breaking', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)
        ->withQueryParams(['zakres' => 'kiedyś'])
        ->test(Earnings::class)
        ->assertOk()
        ->assertSet('range', '2026-09')
        ->assertSee('Zarobek — Wrzesień 2026');
});

test('the table lists the range and nothing else', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['service' => 'Wrześniowa sesja']);
    TrainingSession::factory()->for($this->client)->on('2026-07-09')->create(['service' => 'Lipcowa sesja']);

    Livewire::actingAs($this->trainer)->test(Earnings::class)
        ->assertSee('Wrześniowa sesja')
        ->assertDontSee('Lipcowa sesja')
        ->call('show', '2026-07')
        ->assertSee('Lipcowa sesja')
        ->assertDontSee('Wrześniowa sesja');
});

test('an empty month says so, an empty year says something else', function () {
    Livewire::actingAs($this->trainer)->test(Earnings::class)
        ->assertSee('Żadnej wbitej sesji w tym miesiącu.')
        ->call('show', '2026')
        ->assertSee('Żadnej wbitej sesji w tym roku.');
});

test('another trainer\'s sessions are not counted here', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create();
    TrainingSession::factory()->for($theirs)->on('2026-09-09')->create(['price' => 99000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Earnings::class)
        ->assertSee('200 zł')
        ->assertDontSee('990 zł');
});
