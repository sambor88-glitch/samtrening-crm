<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Actions\SendMonthlyStatement;
use App\Domain\Messaging\Mail\MonthlyStatement;
use App\Domain\Messaging\MessageNotPossible;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Payments;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));
    $this->seed(MessageTemplateSeeder::class);

    $this->trainer = User::factory()->create([
        'name' => 'Katarzyna Samborska',
        'email' => 'kasia@samtrening.com',
        'blik_number' => '600 100 200',
    ]);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'email' => 'magda@example.com',
        'phone' => '+48 600 300 400',
    ]);
});

test('the statement holds the chosen month and not a day more', function () {
    Mail::fake();

    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create([
        'service' => 'Trening personalny 1:1',
        'price' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create([
        'service' => 'Trening personalny 1:1',
        'price' => 20000,
    ]);
    TrainingSession::factory()->for($this->client)->on('2026-08-28')->create([
        'service' => 'Sierpniowa sesja',
        'price' => 90000,
    ]);

    app(SendMonthlyStatement::class)->handle($this->trainer, $this->client, DateRange::fromPrefix('2026-09'));

    Mail::assertQueued(MonthlyStatement::class, function (MonthlyStatement $mail) {
        return $mail->hasTo('magda@example.com')
            && $mail->subjectLine === 'SAMtrening — wrzesień: 400 zł do zapłaty'
            && str_contains($mail->bodyText, 'poniżej sesje z września:')
            && str_contains($mail->bodyText, '· 02.09 — Trening personalny 1:1 — 200 zł')
            && str_contains($mail->bodyText, 'Do zapłaty za wrzesień: 400 zł')
            && ! str_contains($mail->bodyText, 'Sierpniowa');
    });
});

test('the client answers the trainer, not the studio mailbox', function () {
    Mail::fake();

    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['price' => 20000]);

    app(SendMonthlyStatement::class)->handle($this->trainer, $this->client, DateRange::fromPrefix('2026-09'));

    Mail::assertQueued(MonthlyStatement::class, function (MonthlyStatement $mail) {
        return $mail->trainerEmail === 'kasia@samtrening.com'
            && $mail->trainerName === 'Katarzyna Samborska'
            && str_contains($mail->bodyText, 'BLIK na numer 600 100 200')
            && str_contains($mail->bodyText, 'Katarzyna Samborska');
    });
});

test('a client with no e-mail is refused before anything is sent', function () {
    Mail::fake();

    $this->client->update(['email' => null]);

    expect(fn () => app(SendMonthlyStatement::class)
        ->handle($this->trainer, $this->client->fresh(), DateRange::fromPrefix('2026-09')))
        ->toThrow(MessageNotPossible::class, 'Magdalena Wróbel nie ma adresu e-mail na karcie. Uzupełnij go w edycji karty.');

    Mail::assertNothingQueued();
});

test('the button sends one statement per client who owes, and names those it could not', function () {
    Mail::fake();

    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['price' => 20000]);

    $noEmail = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Aleksander Górski',
        'email' => null,
    ]);
    TrainingSession::factory()->for($noEmail)->on('2026-09-03')->create(['price' => 22000]);

    $settled = Client::factory()->for($this->trainer, 'trainer')->create(['email' => 'ktos@example.com']);
    TrainingSession::factory()->for($settled)->on('2026-09-04')->paid()->create();

    Livewire::actingAs($this->trainer)->test(Payments::class)
        ->call('sendStatements')
        ->assertDispatched('toast', message: 'Podsumowania w drodze: 1 klient. Bez e-maila na karcie: Aleksander Górski.');

    Mail::assertQueued(MonthlyStatement::class, 1);

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Wysłał zbiorcze podsumowania')
        ->context->toBe('1 klient · Wrzesień 2026');
});

test('the statement waits in the queue instead of holding up the click', function () {
    expect(new MonthlyStatement('temat', 'treść', 'kasia@samtrening.com', 'Katarzyna Samborska'))
        ->toBeInstanceOf(ShouldQueue::class);
});
