<?php

use App\Domain\Agent\Models\ApiToken;
use Carbon\CarbonImmutable;

/*
 * The door to the agent API — docs/AGENT-API.md §2 and the acceptance list in §6.
 * `issueToken()` and `agentHeaders()` live in tests/Pest.php.
 */

test('a call with no token is refused', function () {
    $this->getJson('/api/agent/v1/clients')
        ->assertStatus(401)
        ->assertExactJson(['error' => 'unauthorized']);
});

test('a call with a token that was never issued is refused', function () {
    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer zle'])
        ->assertStatus(401);
});

test('the Authorization header has to be a bearer token', function () {
    $plain = issueToken();

    $this->getJson('/api/agent/v1/clients', ['Authorization' => $plain])
        ->assertStatus(401);

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Basic '.$plain])
        ->assertStatus(401);
});

test('a valid token gets in', function () {
    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.issueToken()])
        ->assertOk()
        ->assertJsonStructure(['generated_at', 'clients']);
});

test('the plain token is never stored — only its hash', function () {
    [$token, $plain] = ApiToken::issue('Pulpit Maćka', ['crm.read'], 365);

    expect($token->token_hash)->toBe(hash('sha256', $plain))
        ->and($token->token_hash)->not->toContain($plain)
        ->and($token->toArray())->not->toHaveKey('token_hash');

    // Nothing anywhere in the row gives the token back.
    expect(json_encode($token->fresh()->getAttributes()))->not->toContain($plain);
});

test('an issued token carries the prefix that makes a leak recognisable', function () {
    [, $plain] = ApiToken::issue('Pulpit Maćka', ['crm.read'], 365);

    expect($plain)->toStartWith('samtr_');
});

test('a revoked token stops working at once', function () {
    [$token, $plain] = ApiToken::issue('Pulpit Maćka', ['crm.read'], 365);

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])->assertOk();

    $token->forceFill(['revoked_at' => CarbonImmutable::now()])->save();

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])->assertStatus(401);
});

test('an expired token is refused', function () {
    [$token, $plain] = ApiToken::issue('Pulpit Maćka', ['crm.read'], 365);

    $token->forceFill(['expires_at' => CarbonImmutable::now()->subMinute()])->save();

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])->assertStatus(401);
});

test('a token without the scope is refused, and told which one it needed', function () {
    $plain = issueToken(['something.else']);

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])
        ->assertStatus(403)
        ->assertExactJson(['error' => 'forbidden', 'scope' => 'crm.read']);
});

test('a token with no scopes at all opens nothing', function () {
    $plain = issueToken([]);

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])
        ->assertStatus(403);
});

test('a token that never expires keeps working', function () {
    $plain = issueToken(['crm.read'], null);

    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])->assertOk();
});

test('last_used_at is written on every call, without touching updated_at', function () {
    [$token, $plain] = ApiToken::issue('Pulpit Maćka', ['crm.read'], 365);

    expect($token->last_used_at)->toBeNull();

    $updatedAt = $token->updated_at;

    $this->travelTo(CarbonImmutable::now()->addHour());
    $this->getJson('/api/agent/v1/clients', ['Authorization' => 'Bearer '.$plain])->assertOk();

    $token->refresh();

    expect($token->last_used_at)->not->toBeNull()
        ->and($token->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('the token opens the agent routes and nothing else', function () {
    $plain = issueToken();

    // The trainer panel still wants a logged-in user; a bearer token is not one.
    $this->get('/pulpit', ['Authorization' => 'Bearer '.$plain])->assertRedirect('/logowanie');
});

test('the agent API refuses to be written to', function () {
    $headers = ['Authorization' => 'Bearer '.issueToken()];

    $this->postJson('/api/agent/v1/clients', [], $headers)->assertStatus(405);
    $this->putJson('/api/agent/v1/summary', [], $headers)->assertStatus(405);
    $this->deleteJson('/api/agent/v1/clients', [], $headers)->assertStatus(405);
});

test('the artisan command issues a working token and prints it once', function () {
    $this->artisan('agent:token', ['name' => 'Pulpit Maćka', '--scope' => ['crm.read'], '--days' => 30])
        ->assertSuccessful();

    $token = ApiToken::query()->sole();

    expect($token->name)->toBe('Pulpit Maćka')
        ->and($token->scopes)->toBe(['crm.read'])
        ->and($token->expires_at->isFuture())->toBeTrue();
});

test('the command refuses to issue a token that opens nothing', function () {
    $this->artisan('agent:token', ['name' => 'Bez zakresu', '--scope' => []])
        ->assertFailed();

    expect(ApiToken::query()->count())->toBe(0);
});

test('--days=0 issues a token with no expiry', function () {
    $this->artisan('agent:token', ['name' => 'Bezterminowy', '--days' => 0])->assertSuccessful();

    expect(ApiToken::query()->sole()->expires_at)->toBeNull();
});
