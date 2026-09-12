<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Admin\StudioRoster;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);

    $this->mine = Client::factory()->for($this->owner, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    $this->hers = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Aleksander Górski']);
});

test('the owner sees every client with the trainer who runs them', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.clients.index'))
        ->assertOk()
        ->assertSee('Wszyscy klienci.')
        ->assertSee('Magdalena Wróbel')
        ->assertSee('Aleksander Górski')
        ->assertSee('Katarzyna Samborska')
        ->assertSee('2 z 2');
});

test('archived clients stay on the list, marked as archived', function () {
    Client::factory()->for($this->kasia, 'trainer')->archived()->create(['name' => 'Dawny Klient']);

    Livewire::actingAs($this->owner)->test(StudioRoster::class)
        ->assertSee('Dawny Klient')
        ->assertSee('Archiwum')
        ->assertSee('3 z 3');
});

test('the trainer filter comes from the accounts that exist', function () {
    $newcomer = User::factory()->create(['name' => 'Bartek Nowak']);
    Client::factory()->for($newcomer, 'trainer')->create(['name' => 'Nowy Klient']);

    Livewire::actingAs($this->owner)->test(StudioRoster::class)
        ->assertSee('Bartek Nowak')
        ->set('trainer', (string) $this->kasia->id)
        ->assertSee('Aleksander Górski')
        ->assertDontSee('Magdalena Wróbel')
        ->assertDontSee('Nowy Klient')
        ->set('trainer', (string) $newcomer->id)
        ->assertSee('Nowy Klient');
});

test('search works across the whole studio', function () {
    Livewire::actingAs($this->owner)->test(StudioRoster::class)
        ->set('search', 'Górski')
        ->assertSee('Aleksander Górski')
        ->assertDontSee('Magdalena Wróbel')
        ->assertSee('1 z 2');
});

test('the balance shown is the client\'s own', function () {
    TrainingSession::factory()->for($this->hers)->create(['price' => 22000]);

    Livewire::actingAs($this->owner)->test(StudioRoster::class)->assertSee('220 zł');
});

test('the card opens from here with the owner\'s full access', function () {
    $this->actingAs($this->owner)
        ->get(route('clients.show', $this->hers))
        ->assertOk()
        ->assertSee('Aleksander Górski');
});

test('a trainer cannot open the studio cabinet', function () {
    $this->actingAs($this->kasia)->get(route('admin.clients.index'))->assertForbidden();
});
