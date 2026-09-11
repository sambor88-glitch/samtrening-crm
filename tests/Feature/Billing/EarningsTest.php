<?php

use App\Domain\Billing\Earnings;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Support\DateRange;

beforeEach(function () {
    $this->earnings = new Earnings;
    $this->trainer = User::factory()->create();
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create(['rate' => 20000]);
});

test('charged cancellations are money but not sessions', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-05')->cancelled()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->noShow()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-11')->waived()->create();

    $summary = $this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($summary->revenue)->toBe(60000)
        ->and($summary->completedSessions)->toBe(1);
});

test('earnings count the money charged, whether it came in or not', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-03')->create();

    $summary = $this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($summary->revenue)->toBe(40000)
        ->and($summary->completedSessions)->toBe(2);
});

test('the range takes both of its edge days and nothing beyond', function () {
    TrainingSession::factory()->for($this->client)->on('2026-08-31')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-30')->create();
    TrainingSession::factory()->for($this->client)->on('2026-10-01')->create();

    $summary = $this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($summary->revenue)->toBe(40000)
        ->and($summary->completedSessions)->toBe(2);
});

test('a year adds up its months', function () {
    TrainingSession::factory()->for($this->client)->on('2026-01-01')->create();
    TrainingSession::factory()->for($this->client)->on('2026-07-15')->create();
    TrainingSession::factory()->for($this->client)->on('2026-12-31')->create();
    TrainingSession::factory()->for($this->client)->on('2027-01-01')->create();

    $summary = $this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026'));

    expect($summary->revenue)->toBe(60000)
        ->and($summary->completedSessions)->toBe(3);
});

test('another trainer earns their own money', function () {
    $other = User::factory()->create();
    $theirClient = Client::factory()->for($other, 'trainer')->create(['rate' => 22000]);

    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create();
    TrainingSession::factory()->for($theirClient)->on('2026-09-02')->create();

    expect($this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'))->revenue)->toBe(20000)
        ->and($this->earnings->forTrainer($other, DateRange::fromPrefix('2026-09'))->revenue)->toBe(22000);
});

test('an archived client keeps counting towards earnings', function () {
    $archived = Client::factory()->for($this->trainer, 'trainer')->archived()->create(['rate' => 18000]);

    TrainingSession::factory()->for($archived)->on('2026-09-04')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-04')->create();

    $summary = $this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($summary->revenue)->toBe(38000)
        ->and($summary->completedSessions)->toBe(2);
});

test('a deleted session is left out of earnings', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-04')->create();
    $deleted = TrainingSession::factory()->for($this->client)->on('2026-09-05')->create();

    $deleted->delete();

    $summary = $this->earnings->forTrainer($this->trainer, DateRange::fromPrefix('2026-09'));

    expect($summary->revenue)->toBe(20000)
        ->and($summary->completedSessions)->toBe(1);
});
