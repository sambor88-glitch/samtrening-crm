<?php

namespace App\Console\Commands;

use App\Domain\Agent\Models\ApiToken;
use Illuminate\Console\Command;

/**
 * Issues a token for the agent API and prints it once — docs/AGENT-API.md §2. There is no screen
 * for this and no way to read a token back: if it is lost, issue another and revoke the old one.
 */
class IssueAgentToken extends Command
{
    protected $signature = 'agent:token
        {name : Who is holding it, e.g. "Pulpit Maćka"}
        {--scope=crm.read : Scope to grant; repeat the option for several}
        {--days=365 : Days until it expires; 0 for a token that never does}';

    protected $description = 'Issue a bearer token for the read-only agent API';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        /** @var list<string> $scopes */
        $scopes = array_values(array_filter((array) $this->option('scope')));

        if ($scopes === []) {
            $this->error('A token with no scope can read nothing. Pass --scope=crm.read.');

            return self::FAILURE;
        }

        [$token, $plain] = ApiToken::issue($this->argument('name'), $scopes, $days > 0 ? $days : null);

        $this->newLine();
        $this->line('  Token dla: <options=bold>'.$token->name.'</>');
        $this->line('  Zakresy:   '.implode(', ', $scopes));
        $this->line('  Wygasa:    '.($token->expires_at?->format('Y-m-d H:i') ?? 'nigdy'));
        $this->newLine();
        $this->line('  <options=bold>'.$plain.'</>');
        $this->newLine();
        $this->warn('  Zapisz go teraz — nie da się go odczytać po raz drugi.');
        $this->newLine();

        return self::SUCCESS;
    }
}
