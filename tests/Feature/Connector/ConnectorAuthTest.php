<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Team\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * Claude's connector: the way in — SC-68, docs/CLAUDE-CONNECTOR.md. These tests walk the same
 * OAuth dance claude.ai does (discovery, registration, consent, code for token with PKCE) with
 * real tokens: Passport::actingAs() would skip the very guard under test.
 */

const CLAUDE_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

beforeEach(function () {
    usePassportKeys();

    $this->owner = User::factory()->owner()->create(['name' => 'Maciej Samborski']);
});

function registerClaude(TestCase $test): string
{
    return $test->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [CLAUDE_CALLBACK],
    ])->assertCreated()->json('client_id');
}

/**
 * The consent screen, as the given account sees it. Returns the verifier claude.ai keeps for later.
 */
function openConsent(TestCase $test, User $user, string $clientId): array
{
    $verifier = Str::random(64);

    $response = $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => CLAUDE_CALLBACK,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'claude-state',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]));

    return [$response, $verifier];
}

/**
 * Approve (with the session's auth token, whether or not the screen showed a button) and swap the
 * code for tokens.
 *
 * @return array<string, mixed>
 */
function approveAndExchange(TestCase $test, string $clientId, string $verifier): array
{
    $location = $test->post('/oauth/authorize', [
        'client_id' => $clientId,
        'auth_token' => session('authToken'),
    ])->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith(CLAUDE_CALLBACK);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('claude-state');

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => CLAUDE_CALLBACK,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json();
}

function connectClaude(TestCase $test, User $user): string
{
    $clientId = registerClaude($test);
    [, $verifier] = openConsent($test, $user, $clientId);

    return approveAndExchange($test, $clientId, $verifier)['access_token'];
}

/**
 * One JSON-RPC call to /mcp, as a fresh request: guards forget the user between calls, the way a
 * new PHP process on the server would.
 */
function mcpCall(TestCase $test, ?string $token, string $method, array $params = []): TestResponse
{
    app('auth')->forgetGuards();

    return $test->withHeaders(array_filter([
        'Authorization' => $token ? 'Bearer '.$token : null,
        'Accept' => 'application/json, text/event-stream',
    ]))->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
}

test('an unauthenticated call points claude.ai at the discovery document', function () {
    $response = mcpCall($this, null, 'tools/list')->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"');

    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'))
        ->assertJsonPath('authorization_servers.0', url('/'));

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
        ->assertJsonPath('token_endpoint', url('/oauth/token'))
        ->assertJsonPath('registration_endpoint', url('/oauth/register'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

test('only claude.ai may be registered as a redirect', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Somebody else',
        'redirect_uris' => ['https://evil.example/callback'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

    registerClaude($this);
});

test('the owner connects Claude and reads the CRM with the token', function () {
    Client::factory()->for($this->owner, 'trainer')->create(['name' => 'Anna Motkowicz']);

    $clientId = registerClaude($this);
    [$consent, $verifier] = openConsent($this, $this->owner, $clientId);

    $consent->assertOk()
        ->assertSee('Połączyć?')
        ->assertSee('Połącz z Claude')
        ->assertSee('przeciwwskazań zdrowotnych');

    $tokens = approveAndExchange($this, $clientId, $verifier);

    expect($tokens)->toHaveKeys(['access_token', 'refresh_token', 'expires_in'])
        ->and($tokens['expires_in'])->toBeLessThanOrEqual(3600);

    mcpCall($this, $tokens['access_token'], 'tools/list')
        ->assertOk()
        ->assertJsonFragment(['name' => 'find_clients'])
        ->assertJsonFragment(['name' => 'get_session_report']);

    mcpCall($this, $tokens['access_token'], 'tools/call', ['name' => 'find_clients', 'arguments' => ['query' => 'motkowicz']])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.clients.0.name', 'Anna Motkowicz');
});

test('on a phone with no CRM session the owner logs in and lands back on the consent screen', function () {
    $clientId = registerClaude($this);
    $authorize = '/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => CLAUDE_CALLBACK,
        'response_type' => 'code',
        'state' => 'claude-state',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]);

    $this->get($authorize)->assertRedirect(route('login'));

    // Back to the same authorization request — Laravel only reorders its query string.
    $back = $this->post(route('login'), ['email' => $this->owner->email, 'password' => 'password'])
        ->assertRedirect()
        ->headers->get('Location');

    expect($back)->toStartWith(url('/oauth/authorize?'))->toContain('client_id='.$clientId)->toContain('state=claude-state');

    $this->get($authorize)->assertOk()->assertSee('Połącz z Claude');
});

test('a trainer is shown the door and a token they get anyway opens nothing', function () {
    $trainer = User::factory()->create();

    $clientId = registerClaude($this);
    [$consent, $verifier] = openConsent($this, $trainer, $clientId);

    $consent->assertOk()
        ->assertSee('tylko dla właściciela studia')
        ->assertDontSee('Połącz z Claude');

    // The screen hides the button; the lock is on /mcp. A hand-made approval still gets a token,
    // and the token is worth nothing.
    $token = approveAndExchange($this, $clientId, $verifier)['access_token'];

    mcpCall($this, $token, 'tools/list')->assertForbidden();
});

test('a blocked owner account stops working on its next call', function () {
    $token = connectClaude($this, $this->owner);

    mcpCall($this, $token, 'tools/list')->assertOk();

    $this->owner->forceFill(['status' => 'blocked'])->save();

    mcpCall($this, $token, 'tools/list')->assertForbidden();
});

test('disconnecting Claude revokes every token at once', function () {
    $token = connectClaude($this, $this->owner);

    mcpCall($this, $token, 'tools/list')->assertOk();

    $this->artisan('samtrening:odlacz-claude')->assertSuccessful();

    mcpCall($this, $token, 'tools/list')->assertUnauthorized();
});

test('a panel session is no key to the connector', function () {
    // The owner logged into the panel in the same browser must not reach /mcp by cookie.
    app('auth')->forgetGuards();

    $this->actingAs($this->owner)
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});
