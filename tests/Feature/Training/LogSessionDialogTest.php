<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Dialogs\LogSessionDialog;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = User::factory()->create();
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
        'next_session_plan' => 'Przysiad — głębokość',
    ]);
});

test('opened from a card the dialog knows the client, the rate and the plan', function () {
    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id)
        ->assertSet('open', true)
        ->assertSet('clientId', $this->client->id)
        ->assertSet('price', '200')
        ->assertSet('plan', 'Przysiad — głębokość')
        ->assertSet('date', now()->toDateString())
        ->assertSee('Stawka klienta: 200 zł');
});

test('opened from the navigation it asks which client, and refuses to guess', function () {
    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session')
        ->assertSet('clientId', null)
        ->assertSee('— wybierz klienta —')
        ->call('save')
        ->assertHasErrors(['clientId' => 'required']);

    expect(TrainingSession::query()->count())->toBe(0);
});

test('a service with its own price overrides the client rate', function () {
    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id)
        ->set('service', 'E-trening — konsultacja')
        ->assertSet('price', '150')
        ->set('service', 'Trening personalny 1:1')
        ->assertSet('price', '200');
});

test('switching to a cancellation charges nothing until you say otherwise', function () {
    $dialog = Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id)
        ->set('kind', SessionKind::Cancelled->value);

    $dialog->assertSet('settlement', PaymentStatus::Waived->value)
        ->assertSet('price', '0')
        ->assertSee('Czy naliczasz?')
        ->assertSee('Nie naliczam');

    $dialog->set('kind', SessionKind::Completed->value)
        ->assertSet('settlement', PaymentStatus::Balance->value)
        ->assertSet('price', '200')
        ->assertSee('Rozliczenie');
});

test('the free cancellation window comes from the studio settings', function () {
    Setting::query()->create(['free_cancellation_hours' => 12]);

    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id)
        ->set('kind', SessionKind::Cancelled->value)
        ->assertSee('Odwołanie na 12 h przed sesją jest bezpłatne');
});

test('a session logged from the dialog shows up on the balance and in the toast', function () {
    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id)
        ->set('notes', 'Dobra forma, bez bólu kolana')
        ->set('plan', 'Martwy ciąg — technika')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('open', false)
        ->assertDispatched('session-logged')
        ->assertDispatched('toast', message: 'Sesja wbita — Magdalena Wróbel, 200 zł. Doliczone do salda.');

    $session = TrainingSession::query()->sole();

    expect($session->price)->toBe(20000)
        ->and($session->notes)->toBe('Dobra forma, bez bólu kolana')
        ->and($this->client->fresh()->next_session_plan)->toBe('Martwy ciąg — technika');
});

test('a second entry on the same day is flagged but still saved', function () {
    TrainingSession::factory()->for($this->client)->on(now()->toDateString())->create();

    $dialog = Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id);

    $dialog->assertSee('Magdalena ma już 1 wpis z tą datą. Sprawdź, czy nie wbijasz tego samego dwa razy.')
        ->assertSee('Zapisz mimo to');

    $dialog->set('price', '150')->call('save')->assertHasNoErrors();

    expect(TrainingSession::query()->count())->toBe(2);
});

test('a cancellation nobody is charged for is saved at zero, whatever is in the amount', function () {
    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session', client: $this->client->id)
        ->set('kind', SessionKind::Cancelled->value)
        ->set('price', '200')
        ->set('settlement', PaymentStatus::Waived->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Odwołanie zapisane — Magdalena Wróbel, 0 zł. Bez naliczenia.');

    expect(TrainingSession::query()->sole()->price)->toBe(0);
});

test('a session cannot be logged onto another trainer\'s client', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create();

    Livewire::actingAs($this->trainer)->test(LogSessionDialog::class)
        ->dispatch('log-session')
        ->set('clientId', $theirs->id)
        ->set('price', '200')
        ->call('save')
        ->assertForbidden();

    expect(TrainingSession::query()->count())->toBe(0);
});
