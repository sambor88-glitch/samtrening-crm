<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Models\MessageTemplate;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Messages;
use Database\Seeders\MessageTemplateSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
    ]);
});

test('the screen previews every template with the chosen client inside it', function () {
    TrainingSession::factory()->for($this->client)->create(['price' => 20000]);

    $this->actingAs($this->trainer)
        ->get(route('messages.index'))
        ->assertOk()
        ->assertSee('Co dostaje klient.')
        ->assertSee('Cześć Magdalena!', false)
        ->assertSee('BLIK na 600 100 200')
        ->assertSee('Katarzyna · SAMtrening')
        // The preview is filled in; the editor below it keeps the raw template on purpose.
        ->assertSee('Cześć {imie}! Sesja {data}', false);
});

test('every SMS says how long it is and what it will cost', function () {
    Livewire::actingAs($this->trainer)->test(Messages::class)
        ->assertSee('UCS-2')
        ->assertSee('wiadomości');
});

test('an edited template is saved and the log says who changed it', function () {
    Livewire::actingAs($this->trainer)->test(Messages::class)
        ->set('bodies.reminder', 'Cześć {imie}, masz {saldo} do rozliczenia. BLIK: {blik}.')
        ->call('save', 'reminder')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Szablon zapisany.');

    expect(MessageTemplate::query()->where('key', 'reminder')->value('body'))
        ->toBe('Cześć {imie}, masz {saldo} do rozliczenia. BLIK: {blik}.');

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zmienił szablon wiadomości')
        ->context->toBe('reminder')
        ->actor_name->toBe('Katarzyna Samborska');
});

test('an empty template is refused', function () {
    Livewire::actingAs($this->trainer)->test(Messages::class)
        ->set('bodies.reminder', '  ')
        ->call('save', 'reminder')
        ->assertHasErrors('bodies.reminder');
});

test('a trainer with no clients still sees the templates, with a note instead of a preview', function () {
    $fresh = User::factory()->create();

    Livewire::actingAs($fresh)->test(Messages::class)
        ->assertSee('Podgląd wypełni się danymi, gdy dodasz pierwszego klienta.')
        ->assertSee('{imie}', false);
});

test('another trainer\'s client cannot be used as the preview', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create(['name' => 'Cudzy Klient']);

    Livewire::actingAs($this->trainer)->test(Messages::class)
        ->set('clientId', $theirs->id)
        ->assertDontSee('Cudzy')
        ->assertSee('{imie}', false);
});
