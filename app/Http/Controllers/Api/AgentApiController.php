<?php

namespace App\Http\Controllers\Api;

use App\Domain\Agent\Queries\ClientList;
use App\Domain\Agent\Queries\MonthSummary;
use App\Http\Controllers\Controller;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The read-only agent API — docs/AGENT-API.md. Two GETs, a bearer token, JSON out and nothing in.
 *
 * Every field that leaves here is listed by hand in the queries behind it. No model is ever
 * serialised whole: a client card holds a phone number, an e-mail address, contraindications and
 * training notes, and none of that may leave the CRM (docs/AGENT-API.md §3). A whitelist that
 * has to be added to deliberately is the only version of this that stays safe as the card grows.
 */
class AgentApiController extends Controller
{
    /**
     * Every client, with the rate and the calendar aliases the dashboard matches events against.
     */
    public function clients(ClientList $clients): JsonResponse
    {
        return response()->json([
            'generated_at' => CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
            'clients' => $clients->handle(),
        ]);
    }

    /**
     * One month of sessions and money. `?month=YYYY-MM`, this month when it is left out.
     */
    public function summary(Request $request, MonthSummary $summary): JsonResponse
    {
        $month = $request->query('month');

        if ($month === null) {
            return response()->json($summary->handle(DateRange::currentMonth()));
        }

        // A year prefix ("2026") is a range DateRange understands and this endpoint does not:
        // the response is shaped as one month, down to a day-by-day array.
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return response()->json([
                'error' => 'invalid_month',
                'message' => 'Parametr `month` ma mieć postać YYYY-MM, na przykład 2026-09.',
            ], 422);
        }

        return response()->json($summary->handle(DateRange::fromPrefix($month)));
    }
}
