<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->bartek = User::factory()->create(['name' => 'Bartek Nowak']);

    $this->herClient = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    $this->hisClient = Client::factory()->for($this->bartek, 'trainer')->create(['name' => 'Aleksander Górski']);
});

test('Kasia does not see Bartek\'s client by swapping the id in the URL', function () {
    $this->actingAs($this->kasia)
        ->get(route('clients.show', $this->hisClient))
        ->assertForbidden()
        ->assertDontSee('Aleksander Górski');
});

test('a trainer opens their own client card', function () {
    $this->actingAs($this->kasia)
        ->get(route('clients.show', $this->herClient))
        ->assertOk()
        ->assertSee('Magdalena Wróbel');
});

test('a client that does not exist is a 404, not a 403', function () {
    $this->actingAs($this->kasia)->get('/klienci/999999')->assertNotFound();
});

test('the owner reaches any card, because the admin panel shows the whole studio anyway', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('clients.show', $this->hisClient))
        ->assertOk();
});

test('the roster query returns one trainer\'s clients and no one else\'s', function () {
    Client::factory()->count(2)->for($this->kasia, 'trainer')->create();

    $roster = Client::query()->forTrainer($this->kasia)->pluck('id');

    expect($roster)->toHaveCount(3)
        ->and($roster)->not->toContain($this->hisClient->id);
});

test('the owner in the trainer panel has the same narrow roster as anyone else', function () {
    $owner = User::factory()->owner()->create();
    Client::factory()->for($owner, 'trainer')->create();

    expect(Client::query()->forTrainer($owner)->count())->toBe(1)
        ->and(Client::query()->count())->toBe(3);
});

test('a session is as visible as the client it belongs to', function () {
    $herSession = TrainingSession::factory()->for($this->herClient)->create();
    $hisSession = TrainingSession::factory()->for($this->hisClient)->create();

    expect(Gate::forUser($this->kasia)->allows('view', $herSession))->toBeTrue()
        ->and(Gate::forUser($this->kasia)->allows('view', $hisSession))->toBeFalse()
        ->and(Gate::forUser($this->kasia)->allows('delete', $hisSession))->toBeFalse();
});

test('an account that is not active sees no client data, not even its own', function () {
    $invited = User::factory()->invited()->create();
    $blocked = User::factory()->blocked()->create();

    $invitedClient = Client::factory()->for($invited, 'trainer')->create();
    $blockedClient = Client::factory()->for($blocked, 'trainer')->create();

    expect(Gate::forUser($invited)->allows('view', $invitedClient))->toBeFalse()
        ->and(Gate::forUser($invited)->allows('viewAny', Client::class))->toBeFalse()
        ->and(Gate::forUser($blocked)->allows('view', $blockedClient))->toBeFalse();

    // A session opened before the owner blocked the account stops working too.
    $this->actingAs($blocked)->get(route('clients.show', $blockedClient))->assertForbidden();
});

test('guests get the login screen instead of a client card', function () {
    $this->get(route('clients.show', $this->herClient))->assertRedirect(route('login'));
});
