<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Actions\DeleteSession;
use App\Domain\Training\Actions\RestoreSession;
use App\Domain\Training\Actions\UpdateSessionPrice;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\ClientCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = User::factory()->create();
    $this->card = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
    ]);
    $this->session = TrainingSession::factory()->for($this->card)->on('2026-09-09')->create([
        'price' => 20000,
        'service' => 'Trening personalny 1:1',
        'notes' => 'Przysiad — głębokość',
    ]);
});

test('the history lists what happened, for how much and how it stands', function () {
    $this->actingAs($this->trainer)
        ->get(route('clients.show', $this->card))
        ->assertSee('Historia treningów')
        ->assertSee('Trening personalny 1:1')
        ->assertSee('Przysiad — głębokość')
        ->assertSee('Na saldzie')
        ->assertSee('Kwotę każdej sesji możesz nadpisać');
});

test('a card with no sessions asks for the first one instead of showing an empty list', function () {
    $fresh = Client::factory()->for($this->trainer, 'trainer')->create();

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $fresh))
        ->assertSee('Jeszcze żadnej wbitej sesji')
        ->assertSee('＋ Wbij pierwszą sesję')
        ->assertDontSee('Kwotę każdej sesji możesz nadpisać');
});

test('an amount edited in place is saved and both values land in the log', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->set('prices.'.$this->session->id, '150')
        ->call('saveSessionPrice', $this->session->id)
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Kwota sesji zapisana: 150 zł.');

    expect($this->session->fresh()->price)->toBe(15000)
        ->and(app(Balance::class)->forClient($this->card))->toBe(15000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zmienił kwotę sesji')
        ->context->toBe('Magdalena Wróbel · 09.09.2026 · 200 zł → 150 zł');
});

test('an amount that is not a number is refused', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->set('prices.'.$this->session->id, 'dużo')
        ->call('saveSessionPrice', $this->session->id)
        ->assertHasErrors('prices.'.$this->session->id);

    expect($this->session->fresh()->price)->toBe(20000);
});

test('the same amount again leaves the log alone', function () {
    $entries = ActivityEntry::query()->count();

    app(UpdateSessionPrice::class)->handle($this->trainer, $this->session, 20000);

    expect(ActivityEntry::query()->count())->toBe($entries);
});

test('deleting takes the session off the balance but not out of the database', function () {
    app(DeleteSession::class)->handle($this->trainer, $this->session);

    expect(app(Balance::class)->forClient($this->card))->toBe(0)
        ->and(TrainingSession::query()->count())->toBe(0)
        ->and(TrainingSession::withTrashed()->count())->toBe(1)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Usunął sesję')
        ->context->toBe('Magdalena Wróbel · 09.09.2026 · 200 zł · zdjęte z salda');
});

test('deleting a session that was already paid does not claim it came off the balance', function () {
    $paid = TrainingSession::factory()->for($this->card)->on('2026-09-10')->paid()->create(['price' => 20000]);

    app(DeleteSession::class)->handle($this->trainer, $paid);

    expect(ActivityEntry::query()->orderByDesc('id')->first()->context)
        ->toBe('Magdalena Wróbel · 10.09.2026 · 200 zł');
});

test('undo brings the session and the balance back, and says so in the log', function () {
    app(DeleteSession::class)->handle($this->trainer, $this->session);
    app(RestoreSession::class)->handle($this->trainer, $this->session);

    expect(app(Balance::class)->forClient($this->card))->toBe(20000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Cofnął usunięcie sesji')
        ->context->toBe('Magdalena Wróbel · 09.09.2026 · 200 zł');
});

test('deleting from the card offers "Cofnij" and the undo puts it back', function () {
    $component = Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card]);

    $component->call('deleteSession', $this->session->id)
        ->assertDispatched('toast', fn (string $event, array $params) => $params['action']['label'] === 'Cofnij'
            && $params['action']['event'] === 'session-restore'
            && $params['action']['params']['session'] === $this->session->id
            && str_contains($params['message'], 'usunięta z karty Magdalena Wróbel.'));

    expect(app(Balance::class)->forClient($this->card))->toBe(0);

    $component->dispatch('session-restore', session: $this->session->id)
        ->assertDispatched('toast', fn (string $event, array $params) => str_contains($params['message'], 'wróciła na kartę.'));

    expect(app(Balance::class)->forClient($this->card))->toBe(20000);
});

test('a session from another card cannot be touched from this one', function () {
    $elsewhere = TrainingSession::factory()
        ->for(Client::factory()->for($this->trainer, 'trainer'))
        ->create();

    $component = Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card]);

    expect(fn () => $component->call('deleteSession', $elsewhere->id))->toThrow(ModelNotFoundException::class);

    expect($elsewhere->fresh()->trashed())->toBeFalse();
});
