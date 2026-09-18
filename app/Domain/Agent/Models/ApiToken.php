<?php

namespace App\Domain\Agent\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A bearer token for the read-only agent API. The plain text exists for exactly as long as it
 * takes `agent:token` to print it; from then on the row holds only its SHA-256 hash.
 *
 * Revoking is a timestamp, not a delete — a token that turned up somewhere it should not have
 * leaves a record of having existed.
 */
class ApiToken extends Model
{
    /**
     * Tells a leaked token apart from any other random string, in a log file or a commit — which
     * is the whole point of a prefix (docs/AGENT-API.md §2).
     */
    public const string PREFIX = 'samtr_';

    protected $fillable = ['name', 'token_hash', 'scopes', 'expires_at'];

    /**
     * Never let the hash out through an accident — a `toArray()` somewhere, a dumped model in an
     * error page. It has no business leaving this class.
     *
     * @var list<string>
     */
    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /**
     * Issue one, and hand back the plain token alongside the row. The caller prints it and
     * forgets it; there is no second chance to read it.
     *
     * @param  list<string>  $scopes
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name, array $scopes, ?int $days = null): array
    {
        $plain = self::PREFIX.Str::random(48);

        $token = self::create([
            'name' => $name,
            'token_hash' => self::hash($plain),
            'scopes' => $scopes,
            'expires_at' => $days === null
                ? null
                : CarbonImmutable::now(config('app.timezone'))->addDays($days),
        ]);

        return [$token, $plain];
    }

    /**
     * The one place that turns a token into what the database stores. Both issuing and checking
     * go through here, so the two can never drift apart.
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Tokens that would be accepted right now: not revoked, not expired.
     *
     * @param  Builder<ApiToken>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(fn (Builder $token) => $token
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', CarbonImmutable::now(config('app.timezone'))));
    }

    /**
     * Whether this token carries a scope. A token with no scopes at all carries none — an empty
     * list is not a wildcard.
     */
    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /**
     * "Last seen" for the token list.
     *
     * `updated_at` deliberately stays put: it means somebody changed this token — renamed it,
     * revoked it — and reading through it is not a change. `saveQuietly` only silences events,
     * so the timestamps have to be turned off by hand.
     */
    public function markUsed(): void
    {
        $this->timestamps = false;

        try {
            $this->forceFill(['last_used_at' => CarbonImmutable::now(config('app.timezone'))])->saveQuietly();
        } finally {
            $this->timestamps = true;
        }
    }
}
