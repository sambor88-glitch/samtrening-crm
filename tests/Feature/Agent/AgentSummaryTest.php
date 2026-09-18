<?php

use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;

/*
 * GET /api/agent/v1/summary — docs/AGENT-API.md §5.
 */

beforeEach(function () {
    // A fixed "today", so "this month" and the arrears snapshot mean the same thing every run.
    $this->travelTo(CarbonImmutable::parse('2026-09-18 09:00:00', config('app.timezone')));
});

test('with no month it summarises the month the studio is in', function () {
    $body = $this->getJson('/api/agent/v1/summary', agentHeaders())->assertOk()->json();

    expect($body['month'])->toBe('2026-09')
        ->and($body['currency'])->toBe('PLN')
        ->and($body)->toHaveKeys(['generated_at', 'sessions', 'revenue_minor', 'by_client', 'by_day']);
});

test('a month that is not a month is refused with 422 — acceptance check 7', function () {
    foreach (['wrzesien', '2026', '2026-13', '2026-00', '26-09', '2026-9'] as $bad) {
        $this->getJson('/api/agent/v1/summary?month='.$bad, agentHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_month');
    }
});

test('by_day holds every day of the month, zeroes included — acceptance check 6', function () {
    TrainingSession::factory()->on('2026-09-03')->create(['price' => 12000]);

    $body = $this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->assertOk()->json();

    expect($body['by_day'])->toHaveCount(30)
        ->and($body['by_day'][0])->toBe(['date' => '2026-09-01', 'done' => 0, 'revenue_minor' => 0])
        ->and($body['by_day'][2])->toBe(['date' => '2026-09-03', 'done' => 1, 'revenue_minor' => 12000])
        ->and($body['by_day'][29]['date'])->toBe('2026-09-30');

    // February is shorter, and a leap year is longer still.
    expect($this->getJson('/api/agent/v1/summary?month=2026-02', agentHeaders())->json('by_day'))
        ->toHaveCount(28);
});

test('cancellations are counted apart and never inside planned', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->on('2026-09-02')->count(3)->create();
    TrainingSession::factory()->for($client)->on('2026-09-04')->noShow()->create();
    TrainingSession::factory()->for($client)->on('2026-09-05')->cancelled()->count(2)->create();

    $sessions = $this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('sessions');

    expect($sessions)->toBe(['done' => 3, 'planned' => 4, 'cancelled' => 2]);
});

test('training given away is due nothing', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->on('2026-09-02')->create(['price' => 20000]);
    TrainingSession::factory()->for($client)->on('2026-09-03')->create([
        'price' => 20000,
        'payment_status' => PaymentStatus::Waived,
    ]);

    expect($this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('revenue_minor.due'))
        ->toBe(20000);
});

test('takings are counted when the money arrived, not when the session was held', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    // Trained in August, paid for in September: August earned it, September took it in.
    TrainingSession::factory()->for($client)->on('2026-08-20')->create(['price' => 20000]);
    TrainingSession::factory()->for($client)->on('2026-08-27')->create(['price' => 20000]);

    app(MarkAsPaid::class)->handle(User::factory()->create(), $client);

    $august = $this->getJson('/api/agent/v1/summary?month=2026-08', agentHeaders())->json('revenue_minor');
    $september = $this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('revenue_minor');

    expect($august['due'])->toBe(40000)
        ->and($august['paid'])->toBe(0)
        ->and($september['due'])->toBe(0)
        ->and($september['paid'])->toBe(40000);
});

test('money paid up front counts as takings on the day it was paid in', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    Prepayment::factory()->for($client)->create(['amount' => 60000, 'paid_on' => '2026-09-05']);

    expect($this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('revenue_minor.paid'))
        ->toBe(60000);
});

test('a prepayment is not counted twice when the sessions it covers are settled', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    Prepayment::factory()->for($client)->create(['amount' => 20000, 'paid_on' => '2026-09-05']);
    TrainingSession::factory()->for($client)->on('2026-09-06')->create(['price' => 20000]);

    app(PrepaymentPool::class)->allocate($client);

    // The money came in once, as the prepayment. Settling the session moves no further cash.
    expect($this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('revenue_minor.paid'))
        ->toBe(20000);
});

test('outstanding is a level, not the difference between the two flows', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    // Owed since the spring, and nothing about September says so.
    TrainingSession::factory()->for($client)->on('2026-04-10')->create(['price' => 20000]);
    TrainingSession::factory()->for($client)->on('2026-09-08')->create(['price' => 20000]);

    $revenue = $this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('revenue_minor');

    expect($revenue['due'])->toBe(20000)
        ->and($revenue['paid'])->toBe(0)
        ->and($revenue['outstanding'])->toBe(40000)
        ->and($revenue['outstanding'])->not->toBe($revenue['due'] - $revenue['paid']);
});

test('by_client splits the month per card', function () {
    $anna = Client::factory()->create(['name' => 'Anna Motkowicz', 'rate' => 12000]);
    $jakub = Client::factory()->create(['name' => 'Jakub Żurek', 'rate' => 20000]);

    TrainingSession::factory()->for($anna)->on('2026-09-02')->count(2)->create(['price' => 12000]);
    TrainingSession::factory()->for($anna)->on('2026-09-09')->cancelled()->create(['price' => 12000]);
    TrainingSession::factory()->for($jakub)->on('2026-09-03')->paid()->create(['price' => 20000]);

    $rows = collect($this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json('by_client'))
        ->keyBy('id');

    expect($rows[$anna->getKey()])->toMatchArray([
        'done' => 2, 'planned' => 2, 'due_minor' => 24000, 'paid_minor' => 0,
    ]);

    expect($rows[$jakub->getKey()])->toMatchArray([
        'done' => 1, 'planned' => 1, 'due_minor' => 20000, 'paid_minor' => 20000,
    ]);
});

test('a month nobody trained in is all zeroes rather than empty', function () {
    $body = $this->getJson('/api/agent/v1/summary?month=2026-07', agentHeaders())->assertOk()->json();

    expect($body['sessions'])->toBe(['done' => 0, 'planned' => 0, 'cancelled' => 0])
        ->and($body['revenue_minor'])->toBe(['due' => 0, 'paid' => 0, 'outstanding' => 0])
        ->and($body['by_client'])->toBe([])
        ->and($body['by_day'])->toHaveCount(31);
});

test('a deleted session leaves the month', function () {
    $client = Client::factory()->create(['rate' => 20000]);

    TrainingSession::factory()->for($client)->on('2026-09-02')->create(['price' => 20000]);
    $deleted = TrainingSession::factory()->for($client)->on('2026-09-03')->create(['price' => 20000]);
    $deleted->delete();

    $body = $this->getJson('/api/agent/v1/summary?month=2026-09', agentHeaders())->json();

    expect($body['sessions']['done'])->toBe(1)
        ->and($body['revenue_minor']['due'])->toBe(20000);
});
