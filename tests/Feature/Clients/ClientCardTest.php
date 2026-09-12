<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\ClientCard;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function () {
    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->card = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 100 200',
        'email' => 'magda@example.com',
        'rate' => 20000,
        'goal' => 'Powrót do formy po ciąży',
        'consent_given' => true,
        'consent_date' => '2026-09-01',
    ]);
});

test('the card says who this is, what they owe and what was agreed', function () {
    $this->card->tags()->create(['label' => 'Zdrowa ciąża', 'variant' => 'accent']);
    TrainingSession::factory()->for($this->card)->create(['price' => 40000]);

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $this->card))
        ->assertOk()
        ->assertSee('Magdalena Wróbel')
        ->assertSee('Zdrowa ciąża')
        ->assertSee('400 zł')
        ->assertSee('+48 600 100 200')
        ->assertSee('magda@example.com')
        ->assertSee('Katarzyna Samborska')
        ->assertSee('Powrót do formy po ciąży')
        ->assertSee('Zgoda odebrana · 01.09.2026')
        ->assertSee('Stawka tego klienta')
        ->assertSee('← Klienci');
});

test('an empty field says what to do instead of showing nothing', function () {
    $bare = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Nowy Klient',
        'phone' => null,
        'email' => null,
        'goal' => null,
        'consent_given' => false,
        'consent_date' => null,
    ]);

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $bare))
        ->assertSee('Do ustalenia na pierwszej sesji.')
        ->assertSee('Brak zgłoszonych.')
        ->assertSee('Nic zaplanowanego — dopisz przy wbijaniu sesji.')
        ->assertSee('BRAK ZGODY — uzupełnij przed pierwszą sesją')
        ->assertSee('Karta założona '.$bare->created_at->format('d.m.Y'));
});

test('the BLIK button shows up only when there is something to ask for', function () {
    $this->actingAs($this->trainer)
        ->get(route('clients.show', $this->card))
        ->assertDontSee('Poproś o BLIK');

    TrainingSession::factory()->for($this->card)->create(['price' => 20000]);

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $this->card))
        ->assertSee('Poproś o BLIK');
});

test('a new rate is logged with both amounts and leaves logged sessions alone', function () {
    $session = TrainingSession::factory()->for($this->card)->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->set('rate', '220')
        ->call('saveRate')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Stawka Magdalena Wróbel ustawiona na 220 zł za sesję.');

    expect($this->card->fresh()->rate)->toBe(22000)
        ->and($session->fresh()->price)->toBe(20000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zmienił stawkę')
        ->context->toBe('Magdalena Wróbel · 200 zł → 220 zł');
});

test('a rate that is not an amount is refused', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->set('rate', 'dużo')
        ->call('saveRate')
        ->assertHasErrors(['rate' => 'numeric']);

    expect($this->card->fresh()->rate)->toBe(20000);
});

test('the card catches up after the dialog saved it', function () {
    $component = Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card]);

    $this->card->update(['name' => 'Magdalena Wróbel-Nowak', 'rate' => 25000]);

    $component->dispatch('client-saved')
        ->assertSet('rate', Money::toInput(25000))
        ->assertSee('Magdalena Wróbel-Nowak');
});

test('another trainer\'s card is a 403, even with the right id', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create(['name' => 'Cudzy Klient']);

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $theirs))
        ->assertForbidden()
        ->assertDontSee('Cudzy Klient');
});
