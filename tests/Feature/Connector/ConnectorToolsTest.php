<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Actions\RecordPrepayment;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;
use App\Mcp\Servers\StudioServer;
use App\Mcp\Tools\FindClients;
use App\Mcp\Tools\GetClient;
use App\Mcp\Tools\GetEarnings;
use App\Mcp\Tools\GetMonthSummary;
use App\Mcp\Tools\GetSessionReport;
use App\Mcp\Tools\ListOutstanding;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/*
 * Claude's connector: what each tool answers — SC-68, docs/CLAUDE-CONNECTOR.md. The numbers come
 * from the same queries as the panel; these tests pin the shape and, above all, what never leaves.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00', 'Europe/Warsaw'));

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
    $this->kasia = User::factory()->create(['name' => 'Kasia Trenerka']);

    $this->anna = Client::factory()->for($this->owner, 'trainer')->create([
        'name' => 'Anna Motkowicz',
        'rate' => 12000,
        'phone' => '600100200',
        'email' => 'anna@example.com',
        'contraindications' => 'Przepuklina L5',
        'trainer_notes' => 'Boli kolano',
        'goal' => 'Maraton',
        'guardian' => 'Jan Motkowicz',
    ]);
    $this->zurek = Client::factory()->for($this->kasia, 'trainer')->create(['name' => 'Jakub Żurek', 'rate' => 15000]);
});

test('find_clients matches a name without Polish letters and an alias', function () {
    StudioServer::actingAs($this->owner)->tool(FindClients::class, ['query' => 'zurek'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->where('clients.0.name', 'Jakub Żurek')
            ->where('clients.0.trainer', 'Kasia Trenerka')
            ->where('clients.0.rate', '150 zł')
            ->etc());

    // "Ania" is not in the name; it is the generated diminutive alias.
    StudioServer::actingAs($this->owner)->tool(FindClients::class, ['query' => 'ania'])
        ->assertOk()
        ->assertSee('Anna Motkowicz')
        ->assertDontSee('Jakub');
});

test('find_clients leaves the archive out unless asked', function () {
    Client::factory()->archived()->for($this->owner, 'trainer')->create(['name' => 'Barbara Dawna']);

    StudioServer::actingAs($this->owner)->tool(FindClients::class)->assertOk()->assertDontSee('Barbara Dawna');
    StudioServer::actingAs($this->owner)->tool(FindClients::class, ['include_archived' => true])->assertOk()->assertSee('Barbara Dawna');
});

test('get_client gives the card without health data, notes or the guardian', function () {
    TrainingSession::factory()->for($this->anna)->on('2026-09-10')->create(['price' => 12000, 'notes' => 'Ból pleców']);
    TrainingSession::factory()->for($this->anna)->on('2026-09-17')->create(['price' => 12000]);

    StudioServer::actingAs($this->owner)->tool(GetClient::class, ['client_id' => $this->anna->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('name', 'Anna Motkowicz')
            ->where('trainer', 'Maciej Samborski')
            ->where('phone', '600100200')
            ->where('owed_minor', 24000)
            ->where('owed', '240 zł')
            ->where('owed_since', '2026-09-10')
            ->where('days_owed', 15)
            ->where('recent_sessions.0.date', '2026-09-17')
            ->etc())
        ->assertDontSee(['Przepuklina', 'Boli kolano', 'Maraton', 'Jan Motkowicz', 'Ból pleców']);
});

test('get_client names a missing id instead of failing quietly', function () {
    StudioServer::actingAs($this->owner)->tool(GetClient::class, ['client_id' => 999])
        ->assertHasErrors(['Nie ma klienta o id 999']);
});

test('list_outstanding lists debtors, biggest first, with the studio total', function () {
    TrainingSession::factory()->for($this->anna)->on('2026-09-01')->create(['price' => 12000]);
    TrainingSession::factory()->for($this->zurek)->count(2)->on('2026-09-20')->create(['price' => 15000]);
    TrainingSession::factory()->paid()->for($this->zurek)->on('2026-09-21')->create(['price' => 15000]);

    StudioServer::actingAs($this->owner)->tool(ListOutstanding::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total_minor', 42000)
            ->where('total', '420 zł')
            ->where('clients.0.name', 'Jakub Żurek')
            ->where('clients.0.trainer', 'Kasia Trenerka')
            ->where('clients.0.sessions', 2)
            ->where('clients.1.name', 'Anna Motkowicz')
            ->where('clients.1.days', 24)
            ->etc());
});

test('get_month_summary reads the month with names and złoty', function () {
    TrainingSession::factory()->for($this->anna)->on('2026-09-10')->create(['price' => 12000]);
    TrainingSession::factory()->paid()->for($this->zurek)->on('2026-09-11')->create(['price' => 15000]);
    TrainingSession::factory()->for($this->zurek)->on('2026-08-11')->create(['price' => 15000]);

    StudioServer::actingAs($this->owner)->tool(GetMonthSummary::class, ['period' => '2026-09'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('month', '2026-09')
            ->where('label', 'Wrzesień 2026')
            ->where('sessions.done', 2)
            ->where('revenue_minor.due', 27000)
            ->where('revenue.due', '270 zł')
            ->where('revenue_minor.outstanding', 27000)
            ->has('by_client', 2)
            ->where('by_client.0.name', 'Anna Motkowicz')
            ->etc());
});

test('a malformed period is sent back with the format Claude should use', function () {
    StudioServer::actingAs($this->owner)->tool(GetMonthSummary::class, ['period' => 'wrzesień'])
        ->assertHasErrors(['RRRR-MM']);

    // A month tool takes no year.
    StudioServer::actingAs($this->owner)->tool(GetMonthSummary::class, ['period' => '2026'])
        ->assertHasErrors(['RRRR-MM']);
});

test('get_earnings splits the studio by trainer', function () {
    TrainingSession::factory()->paid()->for($this->anna)->on('2026-09-10')->create(['price' => 12000]);
    TrainingSession::factory()->for($this->zurek)->on('2026-09-11')->create(['price' => 15000]);

    StudioServer::actingAs($this->owner)->tool(GetEarnings::class, ['period' => '2026'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('label', 'Cały 2026')
            ->where('studio.revenue_minor', 27000)
            ->where('studio.paid_minor', 12000)
            ->where('studio.owed_minor', 15000)
            ->where('trainers.0.trainer', 'Maciej Samborski')
            ->where('trainers.0.revenue', '120 zł')
            ->where('trainers.1.trainer', 'Kasia Trenerka')
            ->where('trainers.1.owed', '150 zł')
            ->etc());
});

test('a prepayment shows as money left on the card', function () {
    // Recording the prepayment is what runs Billing\PrepaymentPool over the owed session.
    TrainingSession::factory()->for($this->anna)->on('2026-09-10')->create(['price' => 12000]);
    app(RecordPrepayment::class)->handle($this->owner, $this->anna, 50000, '2026-09-01');

    StudioServer::actingAs($this->owner)->tool(GetClient::class, ['client_id' => $this->anna->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('prepaid_left_minor', 38000)
            ->where('owed_minor', 0)
            ->etc());
});

test('the report link opens the accountant CSV once signed, and logs it under the owner', function () {
    TrainingSession::factory()->for($this->anna)->on('2026-09-10')->create(['price' => 12000]);

    $url = null;

    StudioServer::actingAs($this->owner)->tool(GetSessionReport::class, ['period' => '2026-09'])
        ->assertOk()
        ->assertStructuredContent(function ($json) use (&$url) {
            $url = $json->toArray()['url'];
            $json->where('label', 'Wrzesień 2026')->etc();
        });

    expect($url)->toContain('/raport/sesje')->toContain('signature=');

    // Opened on a phone, in a browser where nobody is logged in.
    app('auth')->forgetGuards();

    $csv = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

    expect($csv)->toContain('Anna Motkowicz')->toContain('Maciej Samborski');
    expect(ActivityEntry::query()->where('action', 'Wyeksportował CSV')->where('user_id', $this->owner->getKey())->exists())->toBeTrue();
});

test('a report link is dead when tampered with, expired, or issued to a blocked owner', function () {
    $url = URL::temporarySignedRoute('reports.sessions', now()->addMinutes(15), ['period' => '2026-09', 'by' => $this->owner->getKey()]);

    $this->get(str_replace('2026-09', '2026-08', $url))->assertForbidden();

    $trainerUrl = URL::temporarySignedRoute('reports.sessions', now()->addMinutes(15), ['period' => '2026-09', 'by' => $this->kasia->getKey()]);
    $this->get($trainerUrl)->assertForbidden();

    $this->travel(16)->minutes();
    $this->get($url)->assertForbidden();
});

test('every tool says it only reads', function () {
    foreach ([FindClients::class, GetClient::class, ListOutstanding::class, GetMonthSummary::class, GetEarnings::class, GetSessionReport::class] as $tool) {
        expect(app($tool)->toArray()['annotations'])->toMatchArray(['readOnlyHint' => true]);
    }
});
