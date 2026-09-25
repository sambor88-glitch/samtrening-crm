<?php

namespace App\Mcp\Tools;

use App\Domain\Billing\Earnings;
use App\Domain\Billing\EarningsSummary;
use App\Domain\Team\Models\User;
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
 * The Zarobki screen for every trainer at once, plus the studio line — the same `Billing\Earnings`
 * arithmetic, so the numbers match the panel to the grosz.
 */
#[Name('get_earnings')]
#[Title('Zarobki trenerów')]
#[Description('Zarobki za miesiąc albo rok, dla każdego trenera i dla całego studia: przychód z sesji wpisanych w okresie (zapłaconych czy nie), liczba odbytych sesji, ile z tego zapłacono, ile jeszcze jest winne, oraz odwołania i nieobecności.')]
#[IsReadOnly]
class GetEarnings extends Tool
{
    use ReadsPeriod;

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
        ];
    }

    public function handle(Request $request, Earnings $earnings): ResponseFactory
    {
        $range = $this->period($request);

        $trainers = User::query()->orderByDesc('is_owner')->orderBy('name')->get();

        return Response::structured([
            'period' => $range->prefix(),
            'label' => $range->label(),
            'studio' => $this->row($earnings->forStudio($range)),
            'trainers' => $trainers->map(fn (User $trainer) => [
                'trainer' => $trainer->name,
                ...$this->row($earnings->forTrainer($trainer, $range)),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EarningsSummary $summary): array
    {
        return [
            'revenue' => Money::format($summary->revenue),
            'completed_sessions' => $summary->completedSessions,
            'paid' => Money::format($summary->paid),
            'owed' => Money::format($summary->owed),
            'missed_sessions' => $summary->missedSessions,
            'missed_revenue' => Money::format($summary->missedRevenue),
            'revenue_minor' => $summary->revenue,
            'paid_minor' => $summary->paid,
            'owed_minor' => $summary->owed,
        ];
    }
}
