<?php

use App\Domain\Agent\Models\ApiToken;
use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Billing\Models\Prepayment;
use App\Domain\Billing\PrepaymentPool;
use App\Domain\Clients\Models\Client;
use App\Domain\Training\Enums\PaymentStatus;
use App\Domain\Training\Models\TrainingSession;
use Carbon\CarbonImmutable;

/*
 * POST /api/agent/v1/payments — docs/AGENT-API.md §9, SC-66.
 */

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-18 12:00', 'Europe/Warsaw'));

    $this->client = Client::factory()->create(['name' => 'Anna Motkowicz', 'rate' => 12000]);
    $this->writeHeaders = ['Authorization' => 'Bearer '.issueToken(['crm.write'])];
});

/** The body the dashboard's agent posts. */
function payment(Client $client, ?string $markedAt = null): array
{
    return [
        'client_id' => $client->getKey(),
        'marked_at' => $markedAt ?? CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
    ];
}

test('the reading token cannot move money', function () {
    // The whole point of a second scope: a leaked read token still only reads.
    $this->postJson('/api/agent/v1/payments', payment($this->client), agentHeaders())
        ->assertStatus(403)
        ->assertJsonPath('scope', 'crm.write');

    expect(TrainingSession::query()->where('payment_status', PaymentStatus::Paid)->count())->toBe(0);
});

test('no token at all is refused', function () {
    $this->postJson('/api/agent/v1/payments', payment($this->client))->assertStatus(401);
});

test('a press of the button settles what the client owed', function () {
    TrainingSession::factory()->for($this->client)->count(2)->create(['price' => 12000]);

    $this->postJson('/api/agent/v1/payments', payment($this->client), $this->writeHeaders)
        ->assertOk()
        ->assertJsonPath('settled_minor', 24000)
        ->assertJsonPath('balance_minor', 0);

    expect(TrainingSession::query()->where('payment_status', PaymentStatus::Paid)->count())->toBe(2)
        ->and(TrainingSession::query()->whereNull('paid_at')->count())->toBe(0);
});

test('posting the same press twice settles nothing the second time', function () {
    // The agent runs on a cycle; the same click reaches the CRM more than once.
    TrainingSession::factory()->for($this->client)->create(['price' => 12000]);
    $body = payment($this->client);

    $this->postJson('/api/agent/v1/payments', $body, $this->writeHeaders)
        ->assertJsonPath('settled_minor', 12000);

    $this->postJson('/api/agent/v1/payments', $body, $this->writeHeaders)
        ->assertOk()
        ->assertJsonPath('settled_minor', 0);
});

test('a session logged after the press is not paid off by it', function () {
    // This is what the timestamp is for. Without it the second post of an old click
    // would settle a training the client has not paid for yet.
    TrainingSession::factory()->for($this->client)->create(['price' => 12000]);
    $body = payment($this->client);

    $this->postJson('/api/agent/v1/payments', $body, $this->writeHeaders)->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-09-18 14:00', 'Europe/Warsaw'));
    $later = TrainingSession::factory()->for($this->client)->create(['price' => 12000]);

    $this->postJson('/api/agent/v1/payments', $body, $this->writeHeaders)
        ->assertJsonPath('settled_minor', 0);

    expect($later->fresh()->payment_status)->toBe(PaymentStatus::Balance);
});

test('a fresh press does settle what came in since', function () {
    TrainingSession::factory()->for($this->client)->create(['price' => 12000]);
    $this->postJson('/api/agent/v1/payments', payment($this->client), $this->writeHeaders)->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-09-18 14:00', 'Europe/Warsaw'));
    TrainingSession::factory()->for($this->client)->create(['price' => 12000]);

    $this->postJson('/api/agent/v1/payments', payment($this->client), $this->writeHeaders)
        ->assertJsonPath('settled_minor', 12000);
});

test('a press from the future is refused', function () {
    $tomorrow = CarbonImmutable::now(config('app.timezone'))->addDay()->toIso8601String();

    $this->postJson('/api/agent/v1/payments', payment($this->client, $tomorrow), $this->writeHeaders)
        ->assertStatus(422)
        ->assertJsonValidationErrors('marked_at');
});

test('a client that does not exist is refused', function () {
    $this->postJson('/api/agent/v1/payments', ['client_id' => 9999, 'marked_at' => now()->toIso8601String()], $this->writeHeaders)
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_id');
});

test('a body missing either field is refused', function () {
    $this->postJson('/api/agent/v1/payments', [], $this->writeHeaders)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_id', 'marked_at']);
});

test('the log names the token, not "System"', function () {
    // "Who put this here" is the first question about an amount that looks wrong.
    TrainingSession::factory()->for($this->client)->create(['price' => 12000]);

    $this->postJson('/api/agent/v1/payments', payment($this->client), $this->writeHeaders)->assertOk();

    $entry = ActivityEntry::query()->latest('id')->first();

    expect($entry->action)->toBe('Odznaczył płatność')
        ->and($entry->actor_name)->toBe('Pulpit Maćka (agent)')
        ->and($entry->user_id)->toBeNull()
        ->and($entry->context)->toContain('Anna Motkowicz');
});

test('a client who owes nothing is answered, not refused', function () {
    $this->postJson('/api/agent/v1/payments', payment($this->client), $this->writeHeaders)
        ->assertOk()
        ->assertJsonPath('settled_minor', 0)
        ->assertJsonPath('settled', '0 zł');
});

test('a revoked writing token stops working at once', function () {
    $plain = issueToken(['crm.write']);
    $headers = ['Authorization' => 'Bearer '.$plain];

    $this->postJson('/api/agent/v1/payments', payment($this->client), $headers)->assertOk();

    ApiToken::query()->update(['revoked_at' => CarbonImmutable::now()]);

    $this->postJson('/api/agent/v1/payments', payment($this->client), $headers)->assertStatus(401);
});

test('money paid up front is left where it is', function () {
    // A prepayment already came in; settling the rest must not count it twice.
    Prepayment::factory()->for($this->client)->create(['amount' => 12000]);
    TrainingSession::factory()->for($this->client)->count(2)->create(['price' => 12000]);
    app(PrepaymentPool::class)->allocate($this->client);

    $this->postJson('/api/agent/v1/payments', payment($this->client), $this->writeHeaders)
        ->assertOk()
        ->assertJsonPath('settled_minor', 12000);
});
