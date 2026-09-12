<?php

use App\Domain\Audit\ActivityLogger;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Admin\StudioDashboard;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->kasia = User::factory()->create(['name' => 'Katarzyna Samborska', 'specialty' => 'Zdrowa ciąża']);
});

test('the studio sees its own month: sessions, clients, arrears and accounts', function () {
    $mine = Client::factory()->for($this->owner, 'trainer')->create();
    $hers = Client::factory()->for($this->kasia, 'trainer')->create();
    Client::factory()->for($this->kasia, 'trainer')->archived()->create();

    TrainingSession::factory()->for($mine)->on('2026-09-02')->paid()->create(['price' => 20000]);
    TrainingSession::factory()->for($hers)->on('2026-09-03')->create(['price' => 22000]);
    TrainingSession::factory()->for($hers)->on('2026-08-30')->create(['price' => 90000]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Studio w liczbach.')
        ->assertSee('Sesje — Wrzesień 2026')
        ->assertSee('Klienci studia')
        ->assertSee('Katarzyna Samborska')
        ->assertSee('Zdrowa ciąża')
        ->assertSee('420 zł');
});

test('the arrears figure stays the same whichever range is shown', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create();
    TrainingSession::factory()->for($client)->on('2026-07-10')->create(['price' => 50000]);

    $dashboard = Livewire::actingAs($this->owner)->test(StudioDashboard::class);

    $dashboard->assertSee('500 zł')
        ->call('show', '2026-09')
        ->assertSee('500 zł')
        ->call('show', '2026')
        ->assertSee('500 zł');
});

test('the year adds the months up while the month shows only its own', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create();
    TrainingSession::factory()->for($client)->on('2026-09-02')->paid()->create(['price' => 20000]);
    TrainingSession::factory()->for($client)->on('2026-02-02')->paid()->create(['price' => 30000]);

    $dashboard = Livewire::actingAs($this->owner)->test(StudioDashboard::class);

    $dashboard->assertSee('200 zł obrotu')
        ->call('show', '2026')
        ->assertSee('500 zł obrotu');
});

test('the five latest changes are on the dashboard, with a way to the rest', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Magdalena Wróbel']);

    foreach (range(1, 7) as $i) {
        app(ActivityLogger::class)->record($this->kasia, 'Wbił sesję', 'Wpis numer '.$i);
    }

    $html = $this->actingAs($this->owner)->get(route('admin.dashboard'))->getContent();

    expect(substr_count($html, 'Wpis numer'))->toBe(5)
        ->and($html)->toContain('Wpis numer 7')
        ->and($html)->not->toContain('Wpis numer 1<')
        ->and($html)->toContain('Cały log →');
});

test('a trainer cannot open the studio dashboard', function () {
    $this->actingAs($this->kasia)->get(route('admin.dashboard'))->assertForbidden();
});

test('the studio export carries the trainer column', function () {
    $client = Client::factory()->for($this->kasia, 'trainer')->create();
    TrainingSession::factory()->for($client)->on('2026-09-02')->create();

    Livewire::actingAs($this->owner)->test(StudioDashboard::class)
        ->call('exportCsv')
        ->assertFileDownloaded('samtrening-studio-2026-09.csv')
        ->assertDispatched('toast', message: 'Eksport studia — Wrzesień 2026, 1 wiersz.');
});
