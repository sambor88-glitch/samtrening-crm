<?php

namespace App\Mcp\Tools;

use App\Domain\Agent\Queries\ClientCard;
use App\Domain\Clients\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_client')]
#[Title('Karta klienta')]
#[Description('Karta jednego klienta: trener, stawka, telefon, e-mail, płatnik faktury, zaległość (owed, dodatnia = klient jest winien) i od kiedy, niewykorzystana wpłata z góry oraz 10 ostatnich sesji. Id weź z find_clients. Notatek treningowych i danych zdrowotnych tu nie ma i nie będzie.')]
#[IsReadOnly]
class GetClient extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->min(1)->required()->description('Id klienta z find_clients.'),
        ];
    }

    public function handle(Request $request, ClientCard $card): Response|ResponseFactory
    {
        $data = $request->validate(['client_id' => ['required', 'integer', 'min:1']]);

        $client = Client::query()->with('trainer:id,name')->find($data['client_id']);

        if (! $client) {
            return Response::error("Nie ma klienta o id {$data['client_id']}. Poszukaj go przez find_clients.");
        }

        return Response::structured($card->handle($client));
    }
}
