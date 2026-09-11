<?php

use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Settings\Models\Setting;
use App\Domain\Training\Models\TrainingSession;

beforeEach(function () {
    $this->balance = new Balance;
});

test('the balance adds up every session that is neither paid nor waived', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->create();              // 200 zł, na saldzie
    TrainingSession::factory()->for($client)->requested()->create(); // 200 zł, poproszono
    TrainingSession::factory()->for($client)->paid()->create();      // zapłacone
    TrainingSession::factory()->for($client)->waived()->create();    // odwołane za darmo

    expect($this->balance->forClient($client))->toBe(40000);
});

test('a charged cancellation and a no-show count, a free cancellation does not', function () {
    $client = Client::factory()->create(['rate' => 18000]);

    TrainingSession::factory()->for($client)->cancelled()->create();
    TrainingSession::factory()->for($client)->noShow()->create();
    TrainingSession::factory()->for($client)->waived()->create();

    expect($this->balance->forClient($client))->toBe(36000);
});

test('the price on the session wins over the client rate', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->create(['price' => 15000]);

    expect($this->balance->forClient($client))->toBe(15000);
});

test('a deleted session drops out of the balance', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->create();
    $deleted = TrainingSession::factory()->for($client)->create();

    $deleted->delete();

    expect($this->balance->forClient($client))->toBe(20000)
        ->and(TrainingSession::withTrashed()->count())->toBe(2);
});

test('archiving a client does not forgive the debt', function () {
    $client = Client::factory()->archived()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->create();

    expect($this->balance->forClient($client))->toBe(20000);
});

test('a client who owes nothing has a balance of zero grosze', function () {
    $client = Client::factory()->create();

    TrainingSession::factory()->for($client)->paid()->create();

    expect($this->balance->forClient($client))->toBe(0);
});

test('a list of clients gets its balances from one query', function () {
    $owing = Client::factory()->create(['rate' => 20000]);
    $settled = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($owing)->count(2)->create();
    TrainingSession::factory()->for($settled)->paid()->create();

    $balances = $this->balance->forClients([$owing->id, $settled->id]);

    expect($balances[$owing->id])->toBe(40000)
        ->and($balances[$settled->id] ?? 0)->toBe(0);
});

test('the debt is counted from the oldest session still owed', function () {
    $client = Client::factory()->create();

    TrainingSession::factory()->for($client)->on(now()->subDays(30)->toDateString())->paid()->create();
    TrainingSession::factory()->for($client)->on(now()->subDays(20)->toDateString())->create();
    TrainingSession::factory()->for($client)->on(now()->subDays(3)->toDateString())->create();

    expect($this->balance->owedSince($client)->toDateString())->toBe(now()->subDays(20)->toDateString())
        ->and($this->balance->daysOwed($client))->toBe(20);
});

test('a debt older than the studio threshold is overdue', function () {
    Setting::query()->create(['reminder_threshold_days' => 14]);

    $client = Client::factory()->create();
    TrainingSession::factory()->for($client)->on(now()->subDays(15)->toDateString())->create();

    expect($this->balance->isOverdue($client))->toBeTrue();
});

test('a fresh debt and no debt at all are not overdue', function () {
    Setting::query()->create(['reminder_threshold_days' => 14]);

    $fresh = Client::factory()->create();
    TrainingSession::factory()->for($fresh)->on(now()->subDays(14)->toDateString())->create();

    $settled = Client::factory()->create();
    TrainingSession::factory()->for($settled)->on(now()->subDays(60)->toDateString())->paid()->create();

    expect($this->balance->isOverdue($fresh))->toBeFalse()
        ->and($this->balance->isOverdue($settled))->toBeFalse()
        ->and($this->balance->daysOwed($settled))->toBeNull();
});
