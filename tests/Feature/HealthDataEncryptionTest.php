<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Support\Facades\DB;

test('client contraindications are encrypted at rest', function () {
    $client = Client::factory()->create(['contraindications' => 'Nadciśnienie, uraz kolana']);

    $raw = DB::table('clients')->where('id', $client->id)->value('contraindications');

    expect($raw)->not->toContain('Nadciśnienie')
        ->and($client->fresh()->contraindications)->toBe('Nadciśnienie, uraz kolana');
});

test('training session notes are encrypted at rest', function () {
    $session = TrainingSession::factory()->create(['notes' => 'Ból w odcinku lędźwiowym']);

    $raw = DB::table('training_sessions')->where('id', $session->id)->value('notes');

    expect($raw)->not->toContain('Ból')
        ->and($session->fresh()->notes)->toBe('Ból w odcinku lędźwiowym');
});

test('deleting a training session keeps the row', function () {
    $session = TrainingSession::factory()->create();

    $session->delete();

    expect(TrainingSession::query()->count())->toBe(0)
        ->and(TrainingSession::withTrashed()->count())->toBe(1);
});
