<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\SessionList;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = User::factory()->create();
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Magdalena Wróbel']);
});

test('the screen lists what was logged, newest first', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create([
        'service' => 'Trening personalny 1:1',
        'notes' => 'Starsza sesja',
        'price' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->paid()->create([
        'service' => 'E-trening — konsultacja',
        'notes' => 'Nowsza sesja',
        'price' => 15000,
    ]);

    $this->actingAs($this->trainer)
        ->get(route('sessions.index'))
        ->assertOk()
        ->assertSee('Co się odbyło.')
        ->assertSeeInOrder(['09.09.2026', 'Nowsza sesja', '150 zł', 'Zapłacone', '02.09.2026', 'Starsza sesja', '200 zł', 'Na saldzie'])
        ->assertSee('Magdalena Wróbel');
});

test('every column carries its mobile label', function () {
    TrainingSession::factory()->for($this->client)->create();

    $html = $this->actingAs($this->trainer)->get(route('sessions.index'))->getContent();

    foreach (['Data', 'Klient', 'Usługa', 'Notatka', 'Kwota', 'Status'] as $label) {
        expect($html)->toContain('data-label="'.$label.'"');
    }
});

test('another trainer\'s sessions are not on this screen', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create(['name' => 'Cudzy Klient']);
    TrainingSession::factory()->for($theirs)->create(['service' => 'Cudza usługa']);
    TrainingSession::factory()->for($this->client)->create(['service' => 'Moja usługa']);

    $this->actingAs($this->trainer)
        ->get(route('sessions.index'))
        ->assertSee('Moja usługa')
        ->assertDontSee('Cudza usługa')
        ->assertDontSee('Cudzy Klient');
});

test('a deleted session drops off the screen but an archived client keeps their history', function () {
    $archived = Client::factory()->for($this->trainer, 'trainer')->archived()->create(['name' => 'Dawny Klient']);
    TrainingSession::factory()->for($archived)->create(['service' => 'Sesja z archiwum']);

    TrainingSession::factory()->for($this->client)->create(['service' => 'Usunięta sesja'])->delete();

    $this->actingAs($this->trainer)
        ->get(route('sessions.index'))
        ->assertSee('Sesja z archiwum')
        ->assertSee('Dawny Klient')
        ->assertDontSee('Usunięta sesja');
});

test('older sessions wait behind "pokaż starsze"', function () {
    TrainingSession::factory()->count(30)->for($this->client)->create();

    $component = Livewire::actingAs($this->trainer)->test(SessionList::class);

    expect(substr_count($component->html(), 'wire:key="session-'))->toBe(25);
    $component->assertSee('25 z 30')->assertSee('Pokaż starsze');

    $component->call('more');

    expect(substr_count($component->html(), 'wire:key="session-'))->toBe(30);
    $component->assertDontSee('Pokaż starsze');
});

test('a trainer who has logged nothing is told where sessions come from', function () {
    Livewire::actingAs($this->trainer)->test(SessionList::class)
        ->assertSee('Nic jeszcze nie wbite')
        ->assertSee('Sesje pojawią się tutaj po pierwszym wbiciu')
        ->assertSee('＋ Wbij sesję');
});

test('a session logged from the dialog lands on the list without a reload', function () {
    $component = Livewire::actingAs($this->trainer)->test(SessionList::class)
        ->assertSee('Nic jeszcze nie wbite');

    TrainingSession::factory()->for($this->client)->create(['service' => 'Świeża sesja']);

    $component->dispatch('session-logged')->assertSee('Świeża sesja');
});
