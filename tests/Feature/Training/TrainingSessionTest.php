<?php

use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Facades\DB;

test('a session is payable until it is paid or waived', function (PaymentStatus $status, bool $payable) {
    $session = TrainingSession::factory()->create(['payment_status' => $status]);

    expect($session->isPayable())->toBe($payable);
})->with([
    'na saldzie' => [PaymentStatus::Balance, true],
    'poproszono o wpłatę' => [PaymentStatus::Requested, true],
    'zapłacone' => [PaymentStatus::Paid, false],
    'darowane' => [PaymentStatus::Waived, false],
]);

test('only a held session counts as completed', function (SessionKind $kind, bool $completed) {
    $session = TrainingSession::factory()->create(['kind' => $kind]);

    expect($session->isCompleted())->toBe($completed);
})->with([
    'odbyta' => [SessionKind::Completed, true],
    'odwołana' => [SessionKind::Cancelled, false],
    'nieobecność' => [SessionKind::NoShow, false],
]);

test('the date column holds a plain day, so ranges behave the same on MySQL and SQLite', function () {
    $session = TrainingSession::factory()->on('2026-09-30')->create();

    expect(DB::table('training_sessions')->where('id', $session->id)->value('date'))->toBe('2026-09-30')
        ->and($session->fresh()->date->toDateString())->toBe('2026-09-30');
});
