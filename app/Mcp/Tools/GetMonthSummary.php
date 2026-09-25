<?php

namespace App\Mcp\Tools;

use App\Domain\Agent\Queries\MonthSummary;
use App\Domain\Clients\Models\Client;
use App\Mcp\Tools\Concerns\ReadsPeriod;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The dashboard's month (docs/AGENT-API.md §5), with names next to the client ids and without
 * the day-by-day array: that one feeds a bar chart, and a conversation has none.
 */
#[Name('get_month_summary')]
#[Title('Podsumowanie miesiąca')]
#[Description('Jeden miesiąc studia: sesje odbyte / wpisane bez odwołanych / odwołane, należne za odbyte sesje (due), wpłaty, które przyszły w tym miesiącu za cokolwiek (paid), i zaległości całego studia na dziś (outstanding — to stan, nie due minus paid). Do tego rozbicie na klientów.')]
#[IsReadOnly]
class GetMonthSummary extends Tool
{
    use ReadsPeriod;

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema, allowYear: false),
        ];
    }

    public function handle(Request $request, MonthSummary $summary): ResponseFactory
    {
        $range = $this->period($request, allowYear: false);
        $month = $summary->handle($range);
        $names = Client::query()->whereKey(array_column($month['by_client'], 'id'))->pluck('name', 'id');
        $money = fn (int $minor) => Money::format($minor);

        return Response::structured([
            'month' => $month['month'],
            'label' => $range->label(),
            'sessions' => $month['sessions'],
            'revenue' => array_map($money, $month['revenue_minor']),
            'revenue_minor' => $month['revenue_minor'],
            'by_client' => array_map(fn (array $row) => [
                'client_id' => $row['id'],
                'name' => $names[$row['id']] ?? null,
                'done' => $row['done'],
                'planned' => $row['planned'],
                'due' => $money($row['due_minor']),
                'paid' => $money($row['paid_minor']),
            ], $month['by_client']),
        ]);
    }
}
