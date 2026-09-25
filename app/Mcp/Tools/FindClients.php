<?php

namespace App\Mcp\Tools;

use App\Domain\Agent\Queries\ClientList;
use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The roster, or the part of it a name points at. Built on the dashboard's `ClientList`, so the
 * balance here is the one the dashboard shows, down to its sign.
 */
#[Name('find_clients')]
#[Title('Znajdź klientów')]
#[Description('Lista klientów studia: id, trener, stawka i saldo. Podaj `query`, żeby zawęzić po imieniu, nazwisku albo aliasie z kalendarza (wielkość liter i polskie znaki nie mają znaczenia). Bez `query` zwraca wszystkich aktywnych. Id z wyniku przekazuj do get_client.')]
#[IsReadOnly]
class FindClients extends Tool
{
    /** A whole roster fits; this only stops a runaway answer if the studio ever grows. */
    private const int LIMIT = 150;

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(100)->description('Fragment imienia, nazwiska albo aliasu, np. "motkowicz" albo "ania".'),
            'include_archived' => $schema->boolean()->description('Także klienci w archiwum. Domyślnie nie.'),
        ];
    }

    public function handle(Request $request, ClientList $clients): ResponseFactory
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'include_archived' => ['nullable', 'boolean'],
        ]);

        $words = $this->words($data['query'] ?? '');
        $trainers = Client::query()->with('trainer:id,name')->get(['id', 'trainer_id'])->pluck('trainer.name', 'id');

        $rows = collect($clients->handle())
            ->filter(fn (array $client) => $client['active'] || ($data['include_archived'] ?? false))
            ->filter(fn (array $client) => $this->matches($client, $words))
            ->values();

        return Response::structured([
            'count' => $rows->count(),
            'truncated' => $rows->count() > self::LIMIT,
            // balance: negative = the client owes, positive = paid up front and not yet trained off.
            'clients' => $rows->take(self::LIMIT)->map(fn (array $client) => [
                'id' => $client['id'],
                'name' => $client['name'],
                'trainer' => $trainers[$client['id']] ?? null,
                'active' => $client['active'],
                'rate' => Money::format($client['rate_minor']),
                'rate_minor' => $client['rate_minor'],
                'balance' => Money::format($client['balance_minor']),
                'balance_minor' => $client['balance_minor'],
                'last_session' => $client['last_session'],
            ])->all(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function words(string $query): array
    {
        return array_values(array_filter(explode(' ', $this->fold($query))));
    }

    /**
     * Every word of the query has to appear in the name or in one of the calendar aliases, so
     * "anna mot" finds Anna Motkowicz and "kasia" finds Katarzyna through her alias.
     *
     * @param  array<string, mixed>  $client
     * @param  list<string>  $words
     */
    private function matches(array $client, array $words): bool
    {
        if ($words === []) {
            return true;
        }

        $haystack = $this->fold($client['name'].' '.implode(' ', $client['calendar_aliases']));

        return collect($words)->every(fn (string $word) => str_contains($haystack, $word));
    }

    /**
     * Str::ascii rather than iconv: iconv transliterates differently on macOS — see the CRM's
     * calendar alias test.
     */
    private function fold(string $text): string
    {
        return Str::lower(Str::ascii(trim($text)));
    }
}
