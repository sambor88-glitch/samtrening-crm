<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\TemplateRenderer;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->renderer = app(TemplateRenderer::class);
    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
    ]);
});

test('the BLIK number is the one of the trainer who runs this client', function () {
    $otherTrainer = User::factory()->create(['name' => 'Bartek Nowak', 'blik_number' => '999 999 999']);
    Client::factory()->for($otherTrainer, 'trainer')->create();

    $context = $this->renderer->contextFor($this->client);

    expect($this->renderer->render('BLIK na {blik}. {trener} · {trenerPelny}', $context))
        ->toBe('BLIK na 600 100 200. Katarzyna · Katarzyna Samborska');
});

test('a trainer without a BLIK number leaves a dash, not an empty hole', function () {
    $this->trainer->update(['blik_number' => null]);

    expect($this->renderer->render('BLIK na {blik}', $this->renderer->contextFor($this->client->fresh())))
        ->toBe('BLIK na —');
});

test('the client fields come from the card and the balance', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-11')->create(['price' => 15000]);

    $context = $this->renderer->contextFor($this->client);

    expect($this->renderer->render('{imie}: sesja {data} — {kwota}, saldo {saldo}', $context))
        ->toBe('Magdalena: sesja 11.09 — 150 zł, saldo 350 zł');
});

test('the month appears in the case the sentence needs', function () {
    $context = $this->renderer->contextFor($this->client, DateRange::fromPrefix('2026-09'));

    expect($this->renderer->render('podsumowanie {miesiac}, za {miesiacB}, sesje we {miesiacW}', $context))
        ->toBe('podsumowanie września, za wrzesień, sesje we wrześniu');
});

test('the session list holds this month only, and names what was not a training', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create([
        'service' => 'Trening personalny 1:1',
        'price' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-09-05')->cancelled()->create([
        'service' => 'Trening personalny 1:1',
        'price' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-09-06')->waived()->create([
        'service' => 'Trening personalny 1:1',
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-09-07')->noShow()->create([
        'service' => 'Trening personalny 1:1',
        'price' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-08-30')->create([
        'service' => 'Sierpniowa sesja',
        'price' => 90000,
    ]);

    $context = $this->renderer->contextFor($this->client, DateRange::fromPrefix('2026-09'));

    expect($context['lista'])->toBe(
        "· 02.09 — Trening personalny 1:1 — 200 zł\n"
        ."· 05.09 — Trening personalny 1:1 — 200 zł · odwołanie po terminie\n"
        ."· 06.09 — Trening personalny 1:1 — 0 zł · odwołanie bez naliczenia\n"
        .'· 07.09 — Trening personalny 1:1 — 200 zł · nieobecność'
    )->and($context['sumaListy'])->toBe('600 zł');
});

test('a month with nothing in it says so instead of leaving a blank', function () {
    expect($this->renderer->contextFor($this->client, DateRange::fromPrefix('2026-07'))['lista'])
        ->toBe('(brak sesji w tym miesiącu)');
});

test('an unknown placeholder is left alone rather than emptied', function () {
    expect($this->renderer->render('Cześć {imie}, {cosInnego}', $this->renderer->contextFor($this->client)))
        ->toBe('Cześć Magdalena, {cosInnego}');
});
