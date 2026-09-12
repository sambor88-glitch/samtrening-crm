<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Actions\LogSession;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;

beforeEach(function () {
    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'rate' => 20000,
    ]);
    $this->action = app(LogSession::class);
});

function logSession(array $overrides = []): TrainingSession
{
    return test()->action->handle(test()->trainer, test()->client, array_merge([
        'date' => '2026-09-12',
        'service' => 'Trening personalny 1:1',
        'price' => 20000,
        'kind' => SessionKind::Completed,
        'payment_status' => PaymentStatus::Balance,
        'notes' => null,
    ], $overrides));
}

test('a session put on the balance raises it by exactly its price', function () {
    $session = logSession();

    expect($session->kind)->toBe(SessionKind::Completed)
        ->and($session->payment_status)->toBe(PaymentStatus::Balance)
        ->and(app(Balance::class)->forClient($this->client))->toBe(20000);

    expect(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Wbił sesję')
        ->context->toBe('Magdalena Wróbel · 200 zł · na saldo');
});

test('a session paid on the spot leaves the balance alone', function () {
    logSession(['payment_status' => PaymentStatus::Paid]);

    expect(app(Balance::class)->forClient($this->client))->toBe(0)
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->context)
        ->toBe('Magdalena Wróbel · 200 zł · zapłacone');
});

test('a cancellation nobody is charged for costs nothing and says so', function () {
    logSession([
        'kind' => SessionKind::Cancelled,
        'payment_status' => PaymentStatus::Waived,
        'price' => 0,
    ]);

    expect(app(Balance::class)->forClient($this->client))->toBe(0)
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Zapisał odwołanie')
        ->context->toBe('Magdalena Wróbel · 0 zł · nie naliczono');
});

test('a late cancellation charged to the balance is money like any other', function () {
    logSession(['kind' => SessionKind::Cancelled, 'payment_status' => PaymentStatus::Balance]);

    expect(app(Balance::class)->forClient($this->client))->toBe(20000)
        ->and(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Zapisał odwołanie');
});

test('a no-show is logged under its own name', function () {
    logSession(['kind' => SessionKind::NoShow]);

    expect(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Zapisał nieobecność')
        ->and(app(Balance::class)->forClient($this->client))->toBe(20000);
});

test('what to do next time lands on the client card, not on the session', function () {
    logSession(['next_session_plan' => 'Martwy ciąg — technika']);

    expect($this->client->fresh()->next_session_plan)->toBe('Martwy ciąg — technika');
});

test('a second click on a bad connection does not log a second session', function () {
    logSession();
    logSession();

    expect(TrainingSession::query()->count())->toBe(1)
        ->and(app(Balance::class)->forClient($this->client))->toBe(20000);
});

test('the same client on the same day for a different amount is a second session', function () {
    logSession();
    logSession(['price' => 15000]);

    expect(TrainingSession::query()->count())->toBe(2);
});

test('the same session logged again minutes later is meant, not a double click', function () {
    logSession();

    $this->travel(6)->seconds();

    logSession();

    expect(TrainingSession::query()->count())->toBe(2)
        ->and(app(Balance::class)->forClient($this->client))->toBe(40000);
});
