<?php

namespace App\Mcp\Tools;

use App\Domain\Billing\Queries\Outstanding;
use App\Domain\Billing\Queries\OutstandingRow;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The owner's arrears screen (admin/zaleglosci), as data. Cumulative on purpose: a debt does not
 * belong to a month, so there is no period to pass.
 */
#[Name('list_outstanding')]
#[Title('Zaległości studia')]
#[Description('Kto jest winien studiu pieniądze, na dziś: klient, trener, kwota, liczba nierozliczonych sesji, najstarsza z nich i ile dni minęło, czy wysłano prośbę o BLIK. Posortowane od największej kwoty, z sumą dla całego studia.')]
#[IsReadOnly]
class ListOutstanding extends Tool
{
    public function handle(Outstanding $outstanding): ResponseFactory
    {
        $rows = $outstanding->forStudio();
        // One query for every trainer name rather than one per row; the models load in place.
        EloquentCollection::make($rows->map(fn (OutstandingRow $row) => $row->client))->load('trainer:id,name');
        $total = $outstanding->totalForStudio();

        return Response::structured([
            'total' => Money::format($total),
            'total_minor' => $total,
            'clients' => $rows->map(fn (OutstandingRow $row) => [
                'client_id' => $row->client->getKey(),
                'name' => $row->client->name,
                'trainer' => $row->client->trainer?->name,
                'owed' => Money::format($row->amount),
                'owed_minor' => $row->amount,
                'sessions' => $row->sessions,
                'oldest_session' => $row->oldestOn->toDateString(),
                'days' => $row->days,
                'blik_requested' => $row->requested,
            ])->all(),
        ]);
    }
}
