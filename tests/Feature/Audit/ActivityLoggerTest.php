<?php

use App\Domain\Audit\ActivityLogger;
use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Team\Models\User;
use Illuminate\Support\Carbon;

test('records who did what and when', function () {
    $this->travelTo(Carbon::parse('2026-09-09 20:14', 'Europe/Warsaw'));
    $trainer = User::factory()->create(['name' => 'Maciej Samborski']);

    app(ActivityLogger::class)->record($trainer, 'Wbił sesję', 'Magdalena Wróbel · 200 zł · na saldo');

    $entry = ActivityEntry::query()->sole();
    expect($entry->user_id)->toBe($trainer->id)
        ->and($entry->actor_name)->toBe('Maciej Samborski')
        ->and($entry->action)->toBe('Wbił sesję')
        ->and($entry->context)->toBe('Magdalena Wróbel · 200 zł · na saldo')
        ->and($entry->happened_at->format('Y-m-d H:i'))->toBe('2026-09-09 20:14');
});

test('keeps the actor name after the account is gone', function () {
    $trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
    app(ActivityLogger::class)->record($trainer, 'Wbiła sesję');

    $trainer->delete();

    $entry = ActivityEntry::query()->sole();
    expect($entry->user_id)->toBeNull()
        ->and($entry->actor_name)->toBe('Katarzyna Samborska');
});

test('records actions taken by the system itself', function () {
    app(ActivityLogger::class)->record(null, 'Wysłał monit SMS', 'Ewa Lisowska · 200 zł');

    expect(ActivityEntry::query()->sole()->actor_name)->toBe('System');
});

test('trims context that would not fit the column', function () {
    app(ActivityLogger::class)->record(null, 'Zmienił kartę klienta', str_repeat('ą', 300));

    expect(mb_strlen(ActivityEntry::query()->sole()->context))->toBe(255);
});
