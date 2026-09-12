<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Livewire\Dialogs\ClientDialog;
use App\Livewire\Trainer\ClientList;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = User::factory()->create();
});

test('the list button opens an empty card at 200 zł', function () {
    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->assertSet('open', false)
        ->dispatch('add-client')
        ->assertSet('open', true)
        ->assertSet('rate', '200')
        ->assertSee('Nowa karta')
        ->assertSee('Zapisz klienta');
});

test('the save button stays dead until the card has a name', function () {
    $dialog = Livewire::actingAs($this->trainer)->test(ClientDialog::class)->dispatch('add-client');

    expect($dialog->html())->toMatch('/id="client-dialog-save"[^>]*disabled/');

    $dialog->set('name', 'Anna Kowalska');

    expect($dialog->html())->not->toMatch('/id="client-dialog-save"[^>]*disabled/');
});

test('the server refuses a nameless card as well', function () {
    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('add-client')
        ->set('name', '   ')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);

    expect(Client::query()->count())->toBe(0);
});

test('a saved card reaches the roster and says so', function () {
    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('add-client')
        ->set('name', 'Anna Kowalska')
        ->set('phone', '+48 600 100 200')
        ->set('rate', '220')
        ->set('consent', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('open', false)
        ->assertDispatched('client-saved')
        ->assertDispatched('toast', message: 'Klient Anna Kowalska dodany.');

    $client = Client::query()->sole();

    expect($client->rate)->toBe(22000)
        ->and($client->trainer_id)->toBe($this->trainer->id);

    Livewire::actingAs($this->trainer)->test(ClientList::class)->assertSee('Anna Kowalska');
});

test('contraindications can be written down before the consent is collected', function () {
    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('add-client')
        ->set('name', 'Anna Kowalska')
        ->set('contraindications', 'Kolano')
        ->set('consent', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Client::query()->sole())
        ->contraindications->toBe('Kolano')
        ->consent_given->toBeFalse();
});

test('an invoice needs a company and a tax number', function () {
    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('add-client')
        ->set('name', 'Anna Kowalska')
        ->set('invoice', true)
        ->call('save')
        ->assertHasErrors(['companyName', 'taxId']);

    expect(Client::query()->count())->toBe(0);
});

test('editing loads the card and writes the change back', function () {
    $client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Anna Kowalska',
        'rate' => 20000,
        'consent_given' => true,
    ]);

    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('edit-client', client: $client->id)
        ->assertSet('name', 'Anna Kowalska')
        ->assertSet('rate', '200')
        ->assertSee('Edycja karty')
        ->assertSee('Zapisz zmiany')
        ->set('rate', '220')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Karta Anna Kowalska zaktualizowana.');

    expect($client->fresh()->rate)->toBe(22000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->context)
        ->toBe('Anna Kowalska · stawka 200 zł → 220 zł');
});

test('another trainer\'s card cannot be opened for editing', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create();

    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('edit-client', client: $theirs->id)
        ->assertForbidden();
});

test('cancelling forgets what was typed', function () {
    Livewire::actingAs($this->trainer)->test(ClientDialog::class)
        ->dispatch('add-client')
        ->set('name', 'Anna Kowalska')
        ->call('close')
        ->assertSet('open', false)
        ->assertSet('name', '');

    expect(Client::query()->count())->toBe(0);
});
