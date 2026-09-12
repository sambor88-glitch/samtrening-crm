<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Queries\DormantClient;
use App\Domain\Clients\Queries\DormantClients;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Queries\WeekGrid;
use App\Domain\Training\Queries\WeekGridRow;
use App\Livewire\Trainer\Dashboard;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * A client whose last session was exactly that many days ago.
 */
function silentFor(User $trainer, string $name, int $days): Client
{
    $client = Client::factory()->for($trainer, 'trainer')->create([
        'name' => $name,
        'phone' => '+48 600 300 400',
    ]);

    TrainingSession::factory()
        ->for($client)
        ->on(CarbonImmutable::now(config('app.timezone'))->subDays($days)->toDateString())
        ->create();

    return $client;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));
    $this->seed(MessageTemplateSeeder::class);

    $this->trainer = User::factory()->create([
        'name' => 'Katarzyna Samborska',
        'blik_number' => '600 100 200',
    ]);
});

test('twenty days of silence is not silence yet, twenty-one is', function () {
    silentFor($this->trainer, 'Rafał Kubiak', 20);
    silentFor($this->trainer, 'Dorota Malec', 21);

    $quiet = app(DormantClients::class)->forTrainer($this->trainer);

    expect($quiet->map(fn (DormantClient $row) => $row->client->name)->all())->toBe(['Dorota Malec']);
});

test('the longest silence comes first', function () {
    silentFor($this->trainer, 'Rafał Kubiak', 27);
    silentFor($this->trainer, 'Dorota Malec', 61);
    silentFor($this->trainer, 'Ewa Lisowska', 35);

    expect(app(DormantClients::class)->forTrainer($this->trainer)
        ->map(fn (DormantClient $row) => $row->client->name.' · '.$row->days)
        ->all())
        ->toBe(['Dorota Malec · 61', 'Ewa Lisowska · 35', 'Rafał Kubiak · 27']);
});

test('a client with no sessions at all is new, not quiet', function () {
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Zofia Nowa']);

    expect(app(DormantClients::class)->forTrainer($this->trainer))->toBeEmpty();
});

test('archived clients and other trainers rosters stay out', function () {
    $archived = silentFor($this->trainer, 'Ewa Zarchiwizowana', 40);
    $archived->update(['archived' => true]);

    silentFor(User::factory()->create(), 'Nie Moja Klientka', 40);

    expect(app(DormantClients::class)->forTrainer($this->trainer))->toBeEmpty();
});

test('a client on the tile is not also in the week grid', function () {
    $quiet = silentFor($this->trainer, 'Dorota Malec', 61);
    $active = silentFor($this->trainer, 'Magdalena Wróbel', 3);

    $rows = app(WeekGrid::class)->forTrainer($this->trainer)->rows
        ->map(fn (WeekGridRow $row) => $row->client->name);

    expect($rows->all())->toBe([$active->name])
        ->and($rows)->not->toContain($quiet->name);
});

test('the tile shows the silence and hands over a text of its own', function () {
    Queue::fake();

    silentFor($this->trainer, 'Dorota Malec', 61);

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->assertSee('Cisza w kalendarzu')
        ->assertSee('Dorota Malec')
        ->assertSee('61 dni')
        ->assertSee('Zaczep SMS-em')
        ->call('nudgeQuiet', Client::query()->where('name', 'Dorota Malec')->value('id'), 61)
        ->assertDispatched('toast', message: 'Zaczepka do Dorota Malec poszła do kolejki.');

    Queue::assertPushed(SendSmsMessage::class, function (SendSmsMessage $job) {
        return $job->phone === '+48 600 300 400'
            && str_contains($job->text, 'Dawno Cię nie było')
            && str_contains($job->text, 'Cześć Dorota!')
            // Nothing is owed here, so the text says nothing about money.
            && ! str_contains($job->text, 'BLIK');
    });

    expect(ActivityEntry::query()->where('action', 'Zaczepił po ciszy')->first())
        ->context->toBe('Dorota Malec · 61 dni bez sesji');
});

test('a nudge that cannot be sent says so and writes nothing', function () {
    Queue::fake();

    $noPhone = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Ewa Bez Telefonu', 'phone' => null]);
    TrainingSession::factory()->for($noPhone)->on('2026-07-01')->create();

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->call('nudgeQuiet', $noPhone->id, 76)
        ->assertDispatched('toast', variant: 'error');

    Queue::assertNothingPushed();

    expect(ActivityEntry::query()->where('action', 'Zaczepił po ciszy')->count())->toBe(0);
});

test('the tile disappears when nobody has gone quiet', function () {
    silentFor($this->trainer, 'Magdalena Wróbel', 3);

    $this->actingAs($this->trainer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Cisza w kalendarzu');
});
