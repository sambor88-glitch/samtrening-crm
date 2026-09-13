<?php

use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendMonthlyStatements;
use App\Domain\Messaging\Mail\MonthlyStatement;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Payments;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * SC-57. The statement lists one month and totals that month, so its recipients have to come
 * from the month too. Picking them by the running balance sent somebody whose whole debt was
 * older an invoice reading "0 zł do zapłaty" over an empty list.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));
    $this->seed(MessageTemplateSeeder::class);

    Mail::fake();

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska', 'blik_number' => '600 100 200']);

    // Trenowała i nie zapłaciła — dostaje.
    $this->anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Kowalska', 'email' => 'anna@example.com']);
    TrainingSession::factory()->for($this->anna)->on('2026-09-03')->create(['price' => 20000]);

    // Trenowała i zapłaciła — nie dostaje.
    $this->beata = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Beata Zapłacona', 'email' => 'beata@example.com']);
    TrainingSession::factory()->for($this->beata)->on('2026-09-04')->paid()->create(['price' => 20000]);

    // Wisi tylko ze sierpnia, we wrześniu nie była — nie dostaje.
    $this->celina = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Celina Zaległa', 'email' => 'celina@example.com']);
    TrainingSession::factory()->for($this->celina)->on('2026-08-05')->create(['price' => 18000]);
});

test('the statement goes to whoever owes for this month, and to nobody else', function () {
    $run = app(SendMonthlyStatements::class)->handle($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($run->sent)->toBe(1)
        ->and($run->skipped)->toBe([]);

    Mail::assertQueued(MonthlyStatement::class, 1);
    Mail::assertQueued(MonthlyStatement::class, fn ($mail) => $mail->hasTo('anna@example.com'));
    Mail::assertNotQueued(MonthlyStatement::class, fn ($mail) => $mail->hasTo('beata@example.com'));
    Mail::assertNotQueued(MonthlyStatement::class, fn ($mail) => $mail->hasTo('celina@example.com'));
});

test('an older debt alone never produces a 0 zł statement', function () {
    // Celina is on the arrears list — she owes 180 zł — and still gets no statement for a month
    // she did not train in. Her debt belongs to the reminder and the Płatności tab.
    expect(app(Outstanding::class)->forTrainer($this->trainer)
        ->contains(fn ($row) => $row->client->is($this->celina)))->toBeTrue();

    app(SendMonthlyStatements::class)->handle($this->trainer, DateRange::fromPrefix('2026-09'));

    Mail::assertNotQueued(MonthlyStatement::class, fn ($mail) => $mail->hasTo('celina@example.com'));
});

test('she does get one for the month she actually trained in', function () {
    app(SendMonthlyStatements::class)->handle($this->trainer, DateRange::fromPrefix('2026-08'));

    Mail::assertQueued(MonthlyStatement::class, 1);
    Mail::assertQueued(MonthlyStatement::class, fn ($mail) => $mail->hasTo('celina@example.com'));
});

test('the button promises exactly as many as go out', function () {
    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->assertSee('Wyślij 1 podsumowanie')
        ->assertSee('Zbiorcze podsumowanie');

    // Two clients owe money — the arrears table shows both — but only one is owed for September.
    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->assertSee('Anna Kowalska')
        ->assertSee('Celina Zaległa');
});

test('with arrears but nothing owed this month the panel disappears rather than offering zero', function () {
    // Only Celina's August debt is left standing.
    $this->anna->sessions()->delete();

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->assertSee('Celina Zaległa')          // dalej na liście zaległości
        ->assertDontSee('Zbiorcze podsumowanie')
        ->assertDontSee('Wyślij 0');
});

test('a client with no e-mail is skipped by name, not silently', function () {
    $bezMaila = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Dorota Bez Maila', 'email' => null]);
    TrainingSession::factory()->for($bezMaila)->on('2026-09-07')->create(['price' => 20000]);

    $run = app(SendMonthlyStatements::class)->handle($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($run->sent)->toBe(1)
        ->and($run->skipped)->toBe(['Dorota Bez Maila']);
});
