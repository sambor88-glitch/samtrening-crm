<?php

namespace App\Http\Middleware;

use App\Domain\Agent\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door to the agent API — docs/AGENT-API.md §2. A bearer token, hashed, matched against
 * `api_tokens`, and checked for the scope the route asks for.
 *
 * The client here is a script, never a browser: no cookies, no session, no CSRF. A failure is
 * JSON with a status code, because whatever is calling reads JSON and nothing else.
 */
class AgentToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $header = (string) $request->header('Authorization');

        if (! str_starts_with($header, 'Bearer ')) {
            return $this->refuse('unauthorized');
        }

        $token = ApiToken::query()
            ->usable()
            ->where('token_hash', ApiToken::hash(substr($header, 7)))
            ->first();

        // Wrong, revoked and expired all answer the same way. Telling them apart would tell
        // somebody guessing which of their guesses was once a real token.
        if (! $token) {
            return $this->refuse('unauthorized');
        }

        if ($scope !== null && ! $token->allows($scope)) {
            return $this->refuse('forbidden', 403, ['scope' => $scope]);
        }

        $token->markUsed();

        // The endpoints read it to name the caller in the activity log.
        $request->attributes->set('api_token', $token);

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function refuse(string $error, int $status = 401, array $extra = []): JsonResponse
    {
        return response()->json(['error' => $error] + $extra, $status);
    }
}
