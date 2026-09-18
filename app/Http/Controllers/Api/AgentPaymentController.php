<?php

namespace App\Http\Controllers\Api;

use App\Domain\Agent\Models\ApiToken;
use App\Domain\Billing\Actions\MarkAsPaid;
use App\Domain\Billing\Balance;
use App\Domain\Clients\Models\Client;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The one thing the agent may write: that a client has paid — SC-66.
 *
 * Nothing here decides anything. The CRM cannot see a BLIK landing on a phone or cash
 * crossing a desk, so the truth about a payment comes from the person who took it; this
 * endpoint only carries their press of a button from the dashboard into the ledger.
 *
 * Kept apart from `AgentApiController` on purpose: that one is read-only and its token
 * stays that way. A token that can move money is a different key with a different scope.
 */
class AgentPaymentController extends Controller
{
    /**
     * Settle everything the client owed when the button was pressed.
     */
    public function store(Request $request, MarkAsPaid $markAsPaid, Balance $balance): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            // The instant the button was pressed. The agent posts on a cycle, so the same
            // press can arrive twice; anything logged after it must not be settled by it.
            'marked_at' => ['required', 'date', 'before_or_equal:now'],
        ], [
            'marked_at.before_or_equal' => 'Moment oznaczenia nie może być z przyszłości.',
        ]);

        $client = Client::query()->findOrFail($data['client_id']);
        $pressedAt = CarbonImmutable::parse($data['marked_at'])->setTimezone(config('app.timezone'));

        $amount = $markAsPaid->handle(
            actor: null,
            client: $client,
            loggedBefore: $pressedAt,
            actorName: $this->caller($request),
        );

        return response()->json([
            'client_id' => $client->getKey(),
            // What this call actually settled. Zero means the press had nothing left to
            // settle — a repeat, or a debt already cleared in the panel. Not an error.
            'settled_minor' => $amount,
            'settled' => Money::format($amount),
            'balance_minor' => -$balance->forClient($client),
            'marked_at' => $pressedAt->toIso8601String(),
        ]);
    }

    /**
     * The token's name, so the activity log says which key moved the money rather than
     * filing it under "System" next to the nightly reminder run.
     */
    private function caller(Request $request): string
    {
        $token = $request->attributes->get('api_token');

        return $token instanceof ApiToken ? $token->name.' (agent)' : 'Agent';
    }
}
