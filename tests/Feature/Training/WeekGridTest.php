<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Enums\SessionKind;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Queries\WeekGrid;
use App\Domain\Training\Queries\WeekGridRow;
use App\Livewire\Trainer\Dashboard;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * The grid for one trainer, keyed by client name so the assertions read like the screen.
 *
 * @return array<string, WeekGridRow>
 */
function grid(User $trainer): array
{
    return app(WeekGrid::class)->forTrainer($trainer)->rows
        ->keyBy(fn (WeekGridRow $row) => $row->client->name)
        ->all();
}

beforeEach(function () {
    // Tuesday of the ISO week 14–20.09.2026.
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Magdalena Wróbel']);
});

test('the week runs Monday to Sunday of the current ISO week', function () {
    $week = app(WeekGrid::class)->forTrainer($this->trainer);

    expect($week->monday->toDateString())->toBe('2026-09-14')
        ->and($week->days)->toHaveCount(7)
        ->and(collect($week->days)->map->toDateString()->all())->toBe([
            '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19', '2026-09-20',
        ])
        ->and($week->label())->toBe('14–20.09.2026');
});

test('the week turns over between Sunday night and Monday morning', function () {
    // 23:30 on Sunday, Polish time — still the week that started on the 14th.
    $this->travelTo(CarbonImmutable::parse('2026-09-20 23:30', 'Europe/Warsaw'));
    expect(app(WeekGrid::class)->forTrainer($this->trainer)->monday->toDateString())->toBe('2026-09-14');

    // An hour later it is Monday, and the grid has moved on.
    $this->travelTo(CarbonImmutable::parse('2026-09-21 00:30', 'Europe/Warsaw'));
    expect(app(WeekGrid::class)->forTrainer($this->trainer)->monday->toDateString())->toBe('2026-09-21');
});

test('a held session fills the day, a cancellation and a no-show mark it', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-14')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-16')->cancelled()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-18')->noShow()->create();

    $days = grid($this->trainer)['Magdalena Wróbel']->days;

    expect($days['2026-09-14'])->toBe(SessionKind::Completed)
        ->and($days['2026-09-16'])->toBe(SessionKind::Cancelled)
        ->and($days['2026-09-18'])->toBe(SessionKind::NoShow)
        ->and($days['2026-09-15'])->toBeNull()
        ->and($days['2026-09-20'])->toBeNull();
});

test('one session held makes the day held, whatever else happened around it', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-16')->cancelled()->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-16')->create();

    expect(grid($this->trainer)['Magdalena Wróbel']->days['2026-09-16'])->toBe(SessionKind::Completed);
});

test('sessions from the weeks around it stay out of the grid', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-13')->create();
    TrainingSession::factory()->for($this->client)->on('2026-09-21')->create();

    expect(grid($this->trainer)['Magdalena Wróbel']->isUntouched())->toBeTrue();
});

test('a row with nothing logged all week is the one that gets the button', function () {
    $busy = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Aleksander Górski']);
    TrainingSession::factory()->for($busy)->on('2026-09-15')->create();

    $rows = grid($this->trainer);

    expect($rows['Magdalena Wróbel']->isUntouched())->toBeTrue()
        ->and($rows['Aleksander Górski']->isUntouched())->toBeFalse();

    $html = $this->actingAs($this->trainer)->get(route('dashboard'))->getContent();

    // The button carries the client, so the dialog opens with them already chosen.
    expect($html)->toContain("\$dispatch('log-session', { client: ".$this->client->getKey().' }')
        ->not->toContain("\$dispatch('log-session', { client: ".$busy->getKey().' }');
});

test('clients who have gone quiet are left to their own tile', function () {
    $quiet = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Dorota Malec']);
    TrainingSession::factory()->for($quiet)->on('2026-08-10')->create();   // 36 dni ciszy

    $borderline = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Rafał Kubiak']);
    TrainingSession::factory()->for($borderline)->on('2026-08-26')->create(); // równo 20 dni

    $rows = grid($this->trainer);

    expect($rows)->not->toHaveKey('Dorota Malec')
        ->and($rows)->toHaveKey('Rafał Kubiak')
        // A client with no session at all was just added — they belong here, not in the tile.
        ->and($rows)->toHaveKey('Magdalena Wróbel');
});

test('archived clients and other trainers rosters stay out', function () {
    Client::factory()->for($this->trainer, 'trainer')->archived()->create(['name' => 'Ewa Zarchiwizowana']);

    $other = User::factory()->create();
    Client::factory()->for($other, 'trainer')->create(['name' => 'Nie Moja Klientka']);

    expect(array_keys(grid($this->trainer)))->toBe(['Magdalena Wróbel']);
});

test('the grid disappears when the trainer has nobody to close the week with', function () {
    $fresh = User::factory()->create(['name' => 'Anna Zielińska']);

    $this->actingAs($fresh)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Domykacz tygodnia');
});

test('the grid redraws once a session is logged from it', function () {
    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->assertSee('Domykacz tygodnia')
        ->assertSee('Wbij sesję');

    TrainingSession::factory()->for($this->client)->on('2026-09-15')->create();

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->dispatch('session-logged')
        ->assertDontSee('Wbij sesję');
});
