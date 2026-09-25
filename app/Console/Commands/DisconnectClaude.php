<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\Passport;

/**
 * Cuts Claude's connector off at once — docs/CLAUDE-CONNECTOR.md. Every access and refresh token
 * Passport has issued is revoked, so the next call from claude.ai gets a 401 and the owner has to
 * go through the consent screen again. Removing the connector on claude.ai does not do this: it
 * forgets the token on their side, it does not kill it on ours.
 */
class DisconnectClaude extends Command
{
    protected $signature = 'samtrening:odlacz-claude';

    protected $description = 'Revoke every token Claude\'s connector holds';

    public function handle(): int
    {
        $tokens = Passport::token()->newQuery()->where('revoked', false)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->where('revoked', false)->update(['revoked' => true]);
        // A code handed out in the last ten minutes would otherwise still buy a fresh token.
        Passport::authCode()->newQuery()->where('revoked', false)->update(['revoked' => true]);

        $this->info("Odłączono Claude: unieważnione tokeny dostępu — {$tokens}.");

        return self::SUCCESS;
    }
}
