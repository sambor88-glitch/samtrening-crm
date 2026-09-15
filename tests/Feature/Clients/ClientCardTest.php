<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Actions\RecordPrepayment;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\ClientCard;
use App\Support\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

test('a prepayment written down on the card pays the balance first and says what is left', function () {
    TrainingSession::factory()->for($this->card)->on(now()->subDays(3)->toDateString())->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->assertSee('Klient zapłacił z góry?')
        ->assertSet('prepaymentDate', now()->toDateString())
        ->set('prepaymentAmount', '1000')
        ->call('recordPrepayment')
        ->assertHasNoErrors()
        ->assertDispatched('toast', message: 'Wpłata z góry 1 000 zł zapisana. Pokryła 200 zł z salda. W puli zostało 800 zł.')
        ->assertSet('prepaymentAmount', '')
        ->assertSee('Zostało z przedpłaty')
        ->assertSee('800 zł')
        ->assertSee('starczy na 4 sesje po 200 zł')
        ->assertDontSee('Klient zapłacił z góry?');

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zapisał wpłatę z góry')
        ->context->toBe('Magdalena Wróbel · 1 000 zł · '.now()->format('d.m.Y').' · pokryła 200 zł salda');
});

test('a prepayment that is not an amount, or comes from the future, is refused', function () {
    Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->set('prepaymentAmount', '0')
        ->call('recordPrepayment')
        ->assertHasErrors(['prepaymentAmount' => 'gt'])
        ->set('prepaymentAmount', 'dużo')
        ->call('recordPrepayment')
        ->assertHasErrors(['prepaymentAmount' => 'numeric'])
        ->set('prepaymentAmount', '500')
        ->set('prepaymentDate', now()->addDay()->toDateString())
        ->call('recordPrepayment')
        ->assertHasErrors(['prepaymentDate' => 'before_or_equal']);

    expect($this->card->prepayments()->count())->toBe(0);
});

test('the history says which sessions are beyond the prepayment and where it ran out', function () {
    foreach ([8, 6, 4, 2] as $daysAgo) {
        TrainingSession::factory()->for($this->card)->on(now()->subDays($daysAgo)->toDateString())->create(['price' => 20000]);
    }

    app(RecordPrepayment::class)->handle($this->trainer, $this->card, 50000, now()->subDays(9)->toDateString());

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $this->card))
        ->assertOk()
        ->assertSeeInOrder([
            'Zostało z przedpłaty', '0 zł',
            'Saldo', '300 zł',
            'Wpłacone z góry', '500 zł',
            'Zeszło na sesje', '500 zł', '2 sesje w całości z puli',
            'Zostało', '0 zł', 'pula wyczerpana — 2 sesje poza nią',
            'Historia treningów',
            'Poza przedpłatą',
            'Poza przedpłatą: 100 zł · 100 zł zeszło z puli',
            'Tu skończyła się przedpłata — wyżej sesje poza pulą',
            'Z przedpłaty',
            'Z przedpłaty',
        ]);
});

test('a client who never paid up front sees no pool on the card', function () {
    TrainingSession::factory()->for($this->card)->create(['price' => 20000]);

    $this->actingAs($this->trainer)
        ->get(route('clients.show', $this->card))
        ->assertSee('Klient zapłacił z góry? Zapisz wpłatę')
        ->assertSee('Wpłata z góry ↓')
        ->assertDontSee('Zostało z przedpłaty')
        ->assertDontSee('Poza przedpłatą')
        ->assertDontSee('Tu skończyła się przedpłata');
});

test('a mistaken prepayment comes off the card with Cofnij and goes back on', function () {
    TrainingSession::factory()->for($this->card)->on(now()->subDays(3)->toDateString())->create(['price' => 20000]);
    $prepayment = app(RecordPrepayment::class)->handle($this->trainer, $this->card, 20000, now()->subDays(5)->toDateString());
    $on = now()->subDays(5)->format('d.m.Y');

    $component = Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card])
        ->call('deletePrepayment', $prepayment->id)
        ->assertDispatched('toast', message: 'Wpłata 200 zł z '.$on.' usunięta z karty Magdalena Wróbel.');

    expect(app(Balance::class)->forClient($this->card))->toBe(20000)
        ->and($prepayment->fresh()->trashed())->toBeTrue();

    $component->dispatch('prepayment-restore', prepayment: $prepayment->id)
        ->assertDispatched('toast', message: 'Przywrócone — wpłata 200 zł z '.$on.' wróciła na kartę.');

    expect(app(Balance::class)->forClient($this->card))->toBe(0)
        ->and(ActivityEntry::query()->orderByDesc('id')->limit(2)->pluck('action')->all())
        ->toBe(['Cofnął usunięcie wpłaty z góry', 'Usunął wpłatę z góry']);
});

test('another client\'s prepayment cannot be taken off from this card', function () {
    $other = Client::factory()->for($this->trainer, 'trainer')->create();
    $theirs = app(RecordPrepayment::class)->handle($this->trainer, $other, 20000, now()->toDateString());

    $card = Livewire::actingAs($this->trainer)->test(ClientCard::class, ['client' => $this->card]);

    // Looked up through this card only, so somebody else's id finds nothing at all.
    expect(fn () => $card->call('deletePrepayment', $theirs->id))->toThrow(ModelNotFoundException::class);

    expect($theirs->fresh()->trashed())->toBeFalse();
});
