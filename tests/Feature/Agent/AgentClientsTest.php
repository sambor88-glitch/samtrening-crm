<?php

use App\Domain\Billing\Models\Prepayment;
use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use App\Domain\Training\Models\TrainingSession;

/*
 * GET /api/agent/v1/clients — docs/AGENT-API.md §4.
 */

test('the roster comes back with the shape the dashboard expects', function () {
    $client = Client::factory()->create(['name' => 'Anna Motkowicz', 'rate' => 12000]);

    $body = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json();

    expect($body['clients'])->toHaveCount(1);

    expect($body['clients'][0])->toMatchArray([
        'id' => $client->getKey(),
        'name' => 'Anna Motkowicz',
        'active' => true,
        'rate_minor' => 12000,
        'currency' => 'PLN',
        'next_session' => null,
    ]);

    expect($body['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/');
});

test('every client has a rate and at least one alias — acceptance check 4', function () {
    Client::factory()->count(5)->create();
    Client::factory()->create(['name' => 'Jakub Żurek']);

    $clients = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients');

    $incomplete = collect($clients)->filter(
        fn (array $client) => $client['rate_minor'] === null || $client['calendar_aliases'] === []
    );

    expect($incomplete)->toHaveCount(0);
});

test('nothing personal beyond the name ever leaves the CRM', function () {
    $client = Client::factory()->create([
        'name' => 'Anna Motkowicz',
        'phone' => '+48 601 234 567',
        'email' => 'anna.motkowicz@example.com',
        'goal' => 'Powrót do biegania po kontuzji',
        'contraindications' => 'Przepuklina kręgosłupa L4-L5',
        'trainer_notes' => 'Bardzo wrażliwa na temat wagi',
        'guardian' => 'Jan Motkowicz',
        'company_name' => 'Motkowicz sp. z o.o.',
        'tax_id' => '6771234567',
    ]);

    TrainingSession::factory()->for($client)->create(['notes' => 'Ból w prawym kolanie']);

    $response = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk();

    // A whitelist: the test fails the day somebody adds a field without thinking about it.
    expect(array_keys($response->json('clients')[0]))->toEqualCanonicalizing([
        'id', 'name', 'active', 'rate_minor', 'currency',
        'calendar_aliases', 'balance_minor', 'last_session', 'next_session',
    ]);

    $body = $response->getContent();

    foreach ([
        '601 234 567', '+48 601 234 567', 'anna.motkowicz@example.com',
        'Powrót do biegania', 'Przepuklina', 'wrażliwa na temat wagi',
        'Jan Motkowicz', 'Motkowicz sp', '6771234567', 'Ból w prawym kolanie',
    ] as $secret) {
        expect($body)->not->toContain($secret);
    }
});

test('a debt is negative and money paid up front is positive', function () {
    $owing = Client::factory()->create(['name' => 'Tomasz Róg', 'rate' => 20000]);
    TrainingSession::factory()->for($owing)->count(2)->create(['price' => 20000]);

    $ahead = Client::factory()->create(['name' => 'Jakub Żurek', 'rate' => 20000]);
    Prepayment::factory()->for($ahead)->create(['amount' => 60000]);

    $settled = Client::factory()->create(['name' => 'Wojciech Solecki', 'rate' => 20000]);
    TrainingSession::factory()->for($settled)->paid()->create(['price' => 20000]);

    $clients = collect($this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients'))
        ->keyBy('name');

    expect($clients['Tomasz Róg']['balance_minor'])->toBe(-40000)
        ->and($clients['Jakub Żurek']['balance_minor'])->toBe(60000)
        ->and($clients['Wojciech Solecki']['balance_minor'])->toBe(0);
});

test('an archived client stays in the list, marked inactive', function () {
    Client::factory()->archived()->create(['name' => 'Była Klientka']);

    $clients = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients');

    expect($clients)->toHaveCount(1)
        ->and($clients[0]['active'])->toBeFalse();
});

test('last_session is the last day somebody actually trained', function () {
    $client = Client::factory()->create();

    TrainingSession::factory()->for($client)->on('2026-09-10')->create();
    TrainingSession::factory()->for($client)->on('2026-09-14')->cancelled()->create();
    TrainingSession::factory()->for($client)->on('2026-09-16')->noShow()->create();

    $clients = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients');

    expect($clients[0]['last_session'])->toBe('2026-09-10');
});

test('a client who has never trained has no last session', function () {
    Client::factory()->create();

    $clients = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients');

    expect($clients[0]['last_session'])->toBeNull();
});

test('the roster covers the whole studio, not one trainer', function () {
    Client::factory()->for(User::factory(), 'trainer')->create(['name' => 'Klient Pierwszy']);
    Client::factory()->for(User::factory(), 'trainer')->create(['name' => 'Klient Drugi']);

    $clients = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients');

    expect($clients)->toHaveCount(2);
});

test('aliases written on the card win over the generated ones', function () {
    Client::factory()->create([
        'name' => 'Małgorzata Lidacka',
        'calendar_aliases' => ['ula gosia'],
    ]);

    $clients = $this->getJson('/api/agent/v1/clients', agentHeaders())->assertOk()->json('clients');

    expect($clients[0]['calendar_aliases'])->toBe(['ula gosia']);
});
