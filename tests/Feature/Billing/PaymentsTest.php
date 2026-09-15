<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Actions\RecordPrepayment;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Payments;
use Livewire\Livewire;

beforeEach(function () {
    Setting::query()->create(['reminder_threshold_days' => 14]);

    $this->trainer = User::factory()->create();
    $this->magda = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Magdalena Wróbel']);
    $this->olek = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Aleksander Górski']);
});

test('the screen lists who owes what, the biggest debt first', function () {
    TrainingSession::factory()->for($this->magda)->on(now()->subDays(20)->toDateString())->create(['price' => 20000]);
    TrainingSession::factory()->for($this->magda)->on(now()->subDays(3)->toDateString())->create(['price' => 20000]);
    TrainingSession::factory()->for($this->olek)->on(now()->subDays(5)->toDateString())->create(['price' => 22000]);

    $this->actingAs($this->trainer)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertSee('Kto zapłacił.')
        ->assertSeeInOrder(['Magdalena Wróbel', '2 sesje', '400 zł', 'Aleksander Górski', '1 sesja', '220 zł'])
        ->assertSee('Nierozliczone łącznie')
        ->assertSee('620 zł');
});

test('a debt older than the studio threshold is called out, a fresh one is not', function () {
    TrainingSession::factory()->for($this->magda)->on(now()->subDays(20)->toDateString())->create(['price' => 20000]);
    TrainingSession::factory()->for($this->olek)->on(now()->subDays(5)->toDateString())->create(['price' => 22000]);

    $html = $this->actingAs($this->trainer)->get(route('payments.index'))->getContent();

    expect($html)->toContain('po terminie · 20 dni')
        ->and(substr_count($html, 'po terminie'))->toBe(1);
});

test('a client who was asked for BLIK is marked as asked', function () {
    TrainingSession::factory()->for($this->magda)->create([
        'price' => 20000,
        'payment_status' => PaymentStatus::Requested,
    ]);

    $this->actingAs($this->trainer)
        ->get(route('payments.index'))
        ->assertSee('Poproszono')
        ->assertSee('Prośby bez wpłaty');
});

test('marking a payment clears the balance, drops the client off the list and lands in the log', function () {
    TrainingSession::factory()->for($this->magda)->create(['price' => 20000]);
    TrainingSession::factory()->for($this->magda)->create([
        'price' => 20000,
        'payment_status' => PaymentStatus::Requested,
    ]);
    $untouched = TrainingSession::factory()->for($this->olek)->create(['price' => 22000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->assertSee('Magdalena Wróbel')
        ->call('markPaid', $this->magda->id)
        ->assertDispatched('toast', message: 'Magdalena Wróbel — 400 zł odznaczone jako zapłacone.')
        ->assertDontSee('Magdalena Wróbel')
        ->assertSee('Aleksander Górski');

    expect(app(Balance::class)->forClient($this->magda))->toBe(0)
        ->and($untouched->fresh()->payment_status)->toBe(PaymentStatus::Balance)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Odznaczył płatność')
        ->context->toBe('Magdalena Wróbel · 400 zł');
});

test('a waived cancellation is not turned into a payment', function () {
    TrainingSession::factory()->for($this->magda)->waived()->create();
    TrainingSession::factory()->for($this->magda)->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)->call('markPaid', $this->magda->id);

    expect($this->magda->sessions()->where('payment_status', PaymentStatus::Waived)->count())->toBe(1)
        ->and($this->magda->sessions()->where('payment_status', PaymentStatus::Paid)->count())->toBe(1);
});

test('another trainer\'s client cannot be marked as paid from here', function () {
    $theirs = Client::factory()->for(User::factory()->create(), 'trainer')->create();
    TrainingSession::factory()->for($theirs)->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->call('markPaid', $theirs->id)
        ->assertForbidden();

    expect(app(Balance::class)->forClient($theirs))->toBe(20000);
});

test('the month figure counts what came in this month only', function () {
    TrainingSession::factory()->for($this->magda)->on(now()->toDateString())->paid()->create(['price' => 20000]);
    TrainingSession::factory()->for($this->magda)->on(now()->subMonth()->toDateString())->paid()->create(['price' => 50000]);

    $this->actingAs($this->trainer)
        ->get(route('payments.index'))
        ->assertSee('200 zł')
        ->assertDontSee('500 zł');
});

test('the bulk statement button counts the Polish way', function () {
    // Dates pinned to this month: since SC-57 the button counts recipients of *this month's*
    // statement, and the factory's default date wanders up to thirty days back.
    TrainingSession::factory()->for($this->magda)->on(now()->toDateString())->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)->assertSee('Wyślij 1 podsumowanie');

    TrainingSession::factory()->for($this->olek)->on(now()->toDateString())->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Payments::class)->assertSee('Wyślij 2 podsumowania');
});

test('nothing outstanding says exactly that', function () {
    TrainingSession::factory()->for($this->magda)->paid()->create();

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->assertSee('Nic nierozliczonego.')
        ->assertSee('Wszystkie sesje opłacone.')
        ->assertDontSee('Zbiorcze podsumowanie');
});

test('a client paid up in full by a prepayment is off the list, one paid in part owes only the rest', function () {
    foreach ([$this->magda, $this->olek] as $client) {
        TrainingSession::factory()->for($client)->on(now()->subDays(4)->toDateString())->create(['price' => 20000]);
        TrainingSession::factory()->for($client)->on(now()->subDays(2)->toDateString())->create(['price' => 20000]);
    }

    app(RecordPrepayment::class)->handle($this->trainer, $this->magda, 40000, now()->toDateString());
    app(RecordPrepayment::class)->handle($this->trainer, $this->olek, 30000, now()->toDateString());

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->assertDontSee('Magdalena Wróbel')
        ->assertSeeInOrder(['Aleksander Górski', '1 sesja', '100 zł'])
        ->assertViewHas('total', 10000)
        ->call('markPaid', $this->olek->id)
        ->assertDispatched('toast', message: 'Aleksander Górski — 100 zł odznaczone jako zapłacone.');

    expect(app(Balance::class)->forClient($this->olek))->toBe(0);
});

test('the month figure counts what a prepayment paid for as paid', function () {
    TrainingSession::factory()->for($this->magda)->on(now()->toDateString())->create(['price' => 20000]);
    TrainingSession::factory()->for($this->olek)->on(now()->toDateString())->create(['price' => 20000]);

    app(RecordPrepayment::class)->handle($this->trainer, $this->magda, 20000, now()->toDateString());
    app(RecordPrepayment::class)->handle($this->trainer, $this->olek, 5000, now()->toDateString());

    Livewire::actingAs($this->trainer)->test(Payments::class)->assertViewHas('paidThisMonth', 25000);
});
