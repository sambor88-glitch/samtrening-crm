<?php

use App\Domain\Agent\Models\ApiToken;
use App\Domain\Calendar\Queries\PendingSessions;
use App\Domain\Calendar\SessionCandidate;
use App\Domain\Team\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A usable token for the agent API, with the plain text the caller sends — see
 * tests/Feature/Agent and docs/AGENT-API.md §2.
 *
 * @param  list<string>  $scopes
 */
function issueToken(array $scopes = ['crm.read'], ?int $days = 365): string
{
    [, $plain] = ApiToken::issue('Pulpit Maćka', $scopes, $days);

    return $plain;
}

/**
 * The Authorization header the agent sends.
 *
 * @return array<string, string>
 */
function agentHeaders(?string $plain = null): array
{
    return ['Authorization' => 'Bearer '.($plain ?? issueToken())];
}

/**
 * Makes Google answer with these diary entries — title => RFC3339 start.
 * Shared by the calendar tests in tests/Feature/Calendar (SC-65).
 *
 * @param  array<string, string>  $entries
 */
function diary(array $entries): void
{
    Http::fake([
        'www.googleapis.com/calendar/*' => Http::response([
            'items' => collect($entries)->map(fn (string $start, string $title) => [
                'id' => md5($title.$start),
                'summary' => $title,
                'status' => 'confirmed',
                'start' => ['dateTime' => $start],
            ])->values()->all(),
        ]),
    ]);
}

/**
 * @return Collection<int, SessionCandidate>
 */
function pending(User $trainer): Collection
{
    return app(PendingSessions::class)->forTrainer($trainer);
}
