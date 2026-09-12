<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Clients\Models\Client;
use App\Domain\Messaging\Jobs\SendSmsMessage;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Livewire\Trainer\Dashboard;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00', 'Europe/Warsaw'));
    $this->seed(MessageTemplateSeeder::class);

    $this->trainer = User::factory()->create([
        'name' => 'Katarzyna Samborska',
        'blik_number' => '600 100 200',
    ]);
    $this->client = Client::factory()->for($this->trainer, 'trainer')->create([
        'name' => 'Magdalena Wróbel',
        'phone' => '+48 600 300 400',
        'rate' => 20000,
    ]);
});

test('the greeting uses the first name and changes with the state of the balances', function () {
    $this->actingAs($this->trainer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cześć, Katarzyna.')
        ->assertSee('Wszystko rozliczone.')
        ->assertDontSee('Do wbicia i do odzyskania.');

    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);

    $this->actingAs($this->trainer)
        ->get(route('dashboard'))
        ->assertSee('Do wbicia i do odzyskania.')
        ->assertDontSee('Wszystko rozliczone.');
});

test('the stat bar counts the month, the balances and the active clients', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-02')->paid()->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-09')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-08-20')->paid()->create(['price' => 18000]);
    Client::factory()->for($this->trainer, 'trainer')->archived()->create();

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->assertViewHas('summary', fn ($summary) => $summary->completedSessions === 2 && $summary->revenue === 40000)
        ->assertViewHas('owedTotal', 20000)
        ->assertViewHas('clients', 1)
        ->assertSee('Sesje we wrześniu')
        ->assertSee('Zarobek — Wrzesień')
        ->assertSee('100% Twoje')
        ->assertSee('bez archiwalnych');
});

test('the recovery row shows the last session, not the oldest unpaid one', function () {
    TrainingSession::factory()->for($this->client)->on('2026-08-03')->create(['price' => 20000]);
    TrainingSession::factory()->for($this->client)->on('2026-09-11')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->assertSee('2 sesje')
        ->assertSee('· ostatnia 11.09.2026')
        ->assertDontSee('· ostatnia 03.08.2026');
});

test('the recovery list links to the card and stops at six recent sessions', function () {
    TrainingSession::factory()->for($this->client)->on('2026-09-01')->count(8)->create(['price' => 20000]);

    $page = $this->actingAs($this->trainer)->get(route('dashboard'))->assertOk();

    $page->assertSee('Do odzyskania')
        ->assertSee('Magdalena Wróbel')
        ->assertSee('1 600 zł')
        ->assertSee(route('clients.show', $this->client), escape: false)
        ->assertSee('Cała historia →');

    // Six rows in "Ostatnio wbite", whatever the history holds.
    expect(substr_count($page->getContent(), 'wire:key="recent-'))->toBe(6);
});

test('the nudge goes out from the dashboard and lands in the log', function () {
    Queue::fake();

    TrainingSession::factory()->for($this->client)->on('2026-09-02')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->call('remind', $this->client->id)
        ->assertDispatched('toast', message: 'Monit do Magdalena Wróbel poszedł do kolejki.');

    Queue::assertPushed(SendSmsMessage::class, fn (SendSmsMessage $job) => $job->phone === '+48 600 300 400');

    expect(ActivityEntry::query()->where('action', 'Wysłał monit')->first())
        ->context->toBe('Magdalena Wróbel · 200 zł');
});

test('a nudge that cannot be sent says so and changes nothing', function () {
    Queue::fake();

    $noPhone = Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Ewa Lisowska', 'phone' => null]);
    TrainingSession::factory()->for($noPhone)->on('2026-09-02')->create(['price' => 20000]);

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->call('remind', $noPhone->id)
        ->assertDispatched('toast', variant: 'error');

    Queue::assertNothingPushed();

    expect($noPhone->fresh()->last_reminder_at)->toBeNull();
});

test('"Na następny raz" shows the plans and never leaves the trainer', function () {
    $this->client->update(['next_session_plan' => 'Wrócić do martwego ciągu, ale lżej.']);

    $this->actingAs($this->trainer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Na następny raz')
        ->assertSee('Wrócić do martwego ciągu, ale lżej.')
        ->assertSee('nie idzie do klienta');

    // A client without a plan adds no row, and an archived one leaves the section — with nothing
    // left to show, the whole section goes. (The name itself is not asserted: since SC-42 a
    // client with no sessions belongs in the week grid above, which is the point of that grid.)
    Client::factory()->for($this->trainer, 'trainer')->create(['name' => 'Piotr Bez Planu']);
    $this->client->update(['archived' => true]);

    $this->actingAs($this->trainer)
        ->get(route('dashboard'))
        ->assertDontSee('Na następny raz')
        ->assertDontSee('Wrócić do martwego ciągu');
});

test('a fresh trainer sees empty states instead of zeros and errors', function () {
    $fresh = User::factory()->create(['name' => 'Anna Zielińska']);

    $this->actingAs($fresh)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cześć, Anna.')
        ->assertSee('Wszystko rozliczone.')
        ->assertSee('Zero zaległości.')
        ->assertSee('Nic jeszcze nie wbite.')
        ->assertDontSee('Na następny raz');
});

test('the dashboard shows one trainer and never another', function () {
    $other = User::factory()->create(['name' => 'Maciej Samborski']);
    $theirs = Client::factory()->for($other, 'trainer')->create(['name' => 'Aleksander Górski']);
    TrainingSession::factory()->for($theirs)->on('2026-09-09')->create(['price' => 22000]);

    Livewire::actingAs($this->trainer)->test(Dashboard::class)
        ->assertDontSee('Aleksander Górski')
        ->assertViewHas('owedTotal', 0)
        ->assertViewHas('clients', 1);
});
