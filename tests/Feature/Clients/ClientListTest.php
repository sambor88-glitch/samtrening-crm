<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\ClientList;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = User::factory()->create();
});

test('the screen lives at /klienci and hangs the list on it', function () {
    $this->actingAs($this->trainer)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Twoi klienci.')
        ->assertSeeLivewire(ClientList::class);
});

test('the list shows a client with their phone, rate, balance and last session', function () {
    $magda = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 100 200',
        'rate' => 20000,
    ]);
    $magda->tags()->create(['label' => 'Zdrowa ciąża', 'variant' => 'accent']);
    TrainingSession::factory()->for($magda)->on('2026-09-09')->create(['price' => 40000]);

    Client::factory()->for(User::factory()->create(), 'trainer')->create(['name' => 'Cudzy Klient']);

    Livewire::actingAs($this->trainer)->test(ClientList::class)
        ->assertSee('Magdalena Wróbel')
        ->assertSee('+48 600 100 200')
        ->assertSee('Zdrowa ciąża')
        ->assertSee('09.09.2026')
        ->assertSee('200 zł')
        ->assertSee('400 zł')
        ->assertSee('1 z 1')
        ->assertDontSee('Cudzy Klient');
});

test('a client who owes nothing gets a dash instead of a balance', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Spłacony Klient']);
    TrainingSession::factory()->for($client)->paid()->create();

    Livewire::actingAs($this->trainer)->test(ClientList::class)
        ->assertSee('Spłacony Klient')
        ->assertSee('—');
});

test('the search box and the segment narrow the list together', function () {
    $owing = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    TrainingSession::factory()->for($owing)->create(['price' => 20000]);

    $settled = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Aleksander Górski']);
    TrainingSession::factory()->for($settled)->paid()->create();

    Livewire::actingAs($this->trainer)->test(ClientList::class)
        ->set('filter', 'zalegli')
        ->assertSee('Magdalena Wróbel')
        ->assertDontSee('Aleksander Górski')
        ->assertSee('1 z 2')
        ->set('search', 'Górski')
        ->assertDontSee('Magdalena Wróbel')
        ->assertSee('Nikt nie pasuje')
        ->assertSee('Żaden klient nie pasuje do tego filtra.');
});

test('an empty roster invites the trainer to add the first client', function () {
    Livewire::actingAs($this->trainer)->test(ClientList::class)
        ->assertSee('Kartoteka jest pusta')
        ->assertSee('Dodaj pierwszego klienta — zajmie to dwadzieścia sekund.')
        ->assertSee('＋ Dodaj klienta');
});

test('an empty archive says something else than an empty roster, and offers nothing to press', function () {
    Client::factory()->for($this->trainer, 'trainer')->create();

    $page = Livewire::actingAs($this->trainer)->test(ClientList::class)
        ->set('filter', 'archiwum')
        ->assertSee('Archiwum jest puste')
        ->assertSee('Karta trafia tu dopiero po rozliczeniu salda.')
        ->assertDontSee('Kartoteka jest pusta');

    // Adding a client does not fill an archive, so the button is not offered inside this state.
    expect(substr_count($page->html(), 'Dodaj klienta'))->toBe(1);
});

test('the archive shows the archived clients and nobody else', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Aktywna Klientka']);
    Client::factory()->for($this->trainer, 'trainer')->archived()->create(['name' => 'Dawny Klient']);

    Livewire::actingAs($this->trainer)->test(ClientList::class)
        ->set('filter', 'archiwum')
        ->assertSee('Dawny Klient')
        ->assertDontSee('Aktywna Klientka');
});

test('the filter and the search are readable in the address, in Polish', function () {
    Client::factory()->for($this->trainer, 'trainer')->archived()->create(['name' => 'Dawny Klient']);

    Livewire::actingAs($this->trainer)
        ->withQueryParams(['filtr' => 'archiwum', 'szukaj' => 'Dawny'])
        ->test(ClientList::class)
        ->assertSet('filter', 'archiwum')
        ->assertSet('search', 'Dawny')
        ->assertSee('Dawny Klient');
});

test('a made up filter falls back to the active clients instead of blowing up', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Aktywna Klientka']);

    Livewire::actingAs($this->trainer)
        ->withQueryParams(['filtr' => 'wymyslony'])
        ->test(ClientList::class)
        ->assertOk()
        ->assertSee('Aktywna Klientka');
});
