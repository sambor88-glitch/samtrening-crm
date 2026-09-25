<?php

namespace App\Domain\Agent\Queries;

use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Money;

/**
 * One client card as Claude's connector reads it — SC-68, docs/CLAUDE-CONNECTOR.md.
 *
 * Like `ClientList`, every field is written out by hand. The card also holds contraindications,
 * training notes, the goal, the baseline, the plan for next time and the guardian; none of that
 * goes to a language model in another company's cloud, and a field added to the card later stays
 * out until somebody adds it here on purpose. Contact details and the invoice payer do go: the
 * owner asks for a phone number far more often than for anything else on the card.
 */
class ClientCard
{
    /** Enough history to answer "when was she last in" without dumping years of sessions. */
    private const int RECENT_SESSIONS = 10;

    public function __construct(private readonly Balance $balance) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Client $client): array
    {
        $owed = $this->balance->forClient($client);
        $prepayment = $this->balance->prepayment($client);

        return [
            'id' => $client->getKey(),
            'name' => $client->name,
            'trainer' => $client->trainer?->name,
            'active' => ! $client->archived,
            'rate_minor' => (int) $client->rate,
            'rate' => Money::format((int) $client->rate),
            'phone' => $client->phone,
            'email' => $client->email,
            'invoice_payer' => $client->company_name ? [
                'company' => $client->company_name,
                'tax_id' => $client->tax_id,
            ] : null,
            // Positive = the client owes the studio. The dashboard API signs it the other way
            // (docs/AGENT-API.md §4); here it reads the way the panel says it.
            'owed_minor' => $owed,
            'owed' => Money::format($owed),
            'owed_since' => $this->balance->owedSince($client)?->toDateString(),
            'days_owed' => $this->balance->daysOwed($client),
            'prepaid_left_minor' => $prepayment->left,
            'prepaid_left' => Money::format($prepayment->left),
            'recent_sessions' => $this->recentSessions($client),
        ];
    }

    /**
     * The latest sessions, newest first — without their notes, which are health data.
     *
     * @return list<array<string, mixed>>
     */
    private function recentSessions(Client $client): array
    {
        return $client->sessions()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(self::RECENT_SESSIONS)
            ->get(['id', 'date', 'service', 'price', 'kind', 'payment_status'])
            ->map(fn (TrainingSession $session) => [
                'date' => $session->date->toDateString(),
                'service' => $session->service,
                'kind' => $session->kind->label(),
                'price' => Money::format($session->price),
                'payment' => $session->payment_status->label(),
            ])
            ->all();
    }
}
