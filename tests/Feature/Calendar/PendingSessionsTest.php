<?php

use App\Domain\Calendar\CalendarAccessToken;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-18 12:00', 'Europe/Warsaw'));

    config()->set('calendar.client_id', 'id');
    config()->set('calendar.client_secret', 'sekret');
    config()->set('calendar.refresh_token', 'refresh');
    config()->set('calendar.lookback_days', 30);

    Cache::put(CalendarAccessToken::CACHE_KEY, 'dostep', 3600);

    $this->trainer = User::factory()->create();
});

/*
 * `diary()` i `pending()` mieszkają w tests/Pest.php — używa ich też ekran.
 */

test('a training in the diary and not in the CRM is offered', function () {
    $anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz', 'rate' => 12000]);
    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    $candidates = pending($this->trainer);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->client->is($anna))->toBeTrue()
        ->and($candidates[0]->date())->toBe('2026-09-17')
        ->and($candidates[0]->suggestedPrice())->toBe(12000)
        ->and($candidates[0]->isMatched())->toBeTrue();
});

test('a session already logged that day is not offered again', function () {
    $anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    TrainingSession::factory()->for($anna)->on('2026-09-17')->create();

    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    expect(pending($this->trainer))->toHaveCount(0);
});

test('a session logged on another day does not hide a new one', function () {
    $anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    TrainingSession::factory()->for($anna)->on('2026-09-15')->create();

    diary(['Anna trening' => '2026-09-17T10:00:00+02:00']);

    expect(pending($this->trainer))->toHaveCount(1);
});

test('another trainer roster stays on another trainer screen', function () {
    Client::factory()->for(User::factory(), 'trainer')->create(['name' => 'Jakub Żurek']);
    diary(['Jakub Żurek - Trening' => '2026-09-17T10:00:00+02:00']);

    expect(pending($this->trainer))->toHaveCount(0);
});

test('an unrecognised entry is kept, without a client', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    diary(['Przegląd sprzętu' => '2026-09-17T10:00:00+02:00']);

    $candidates = pending($this->trainer);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->isMatched())->toBeFalse()
        ->and($candidates[0]->isAmbiguous())->toBeFalse()
        ->and($candidates[0]->event->title)->toBe('Przegląd sprzętu');
});

test('a title two clients answer to is flagged, never guessed', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Kowalska']);

    diary(['Anna Motkowicz i Anna Kowalska' => '2026-09-17T10:00:00+02:00']);

    $candidates = pending($this->trainer);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->isAmbiguous())->toBeTrue()
        ->and($candidates[0]->ambiguous)->toHaveCount(2)
        ->and($candidates[0]->isMatched())->toBeFalse();
});

test('the window starts at the last logged session', function () {
    $anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    TrainingSession::factory()->for($anna)->on('2026-09-16')->create();

    diary([]);
    pending($this->trainer);

    Http::assertSent(fn ($r) => str_starts_with($r['timeMin'], '2026-09-16'));
});

test('a trainer who has logged nothing gets the whole window', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);

    diary([]);
    pending($this->trainer);

    Http::assertSent(fn ($r) => str_starts_with($r['timeMin'], '2026-08-19'));
});

test('a long silence is capped at the lookback window', function () {
    $anna = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    TrainingSession::factory()->for($anna)->on('2026-01-10')->create();

    diary([]);
    pending($this->trainer);

    Http::assertSent(fn ($r) => str_starts_with($r['timeMin'], '2026-08-19'));
});

test('an unconfigured calendar costs nothing and shows nothing', function () {
    config()->set('calendar.refresh_token', null);
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    Http::fake();

    expect(pending($this->trainer))->toHaveCount(0);

    Http::assertNothingSent();
});

test('candidates come back oldest first, each with a stable key', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Anna Motkowicz']);
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Jakub Żurek']);

    diary([
        'Jakub Żurek - Trening' => '2026-09-17T15:00:00+02:00',
        'Anna trening' => '2026-09-16T10:00:00+02:00',
    ]);

    $candidates = pending($this->trainer);

    expect($candidates[0]->date())->toBe('2026-09-16')
        ->and($candidates[1]->date())->toBe('2026-09-17')
        ->and($candidates[0]->key())->not->toBe($candidates[1]->key());
});
