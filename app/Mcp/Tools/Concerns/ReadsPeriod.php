<?php

namespace App\Mcp\Tools\Concerns;

use App\Support\DateRange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;

/**
 * The `period` argument the reporting tools share: "2026-09" for a month, "2026" for a year,
 * the current month when Claude leaves it out — the same prefixes Support\DateRange reads.
 */
trait ReadsPeriod
{
    private function periodSchema(JsonSchema $schema, bool $allowYear = true): Type
    {
        return $schema->string()
            ->pattern($allowYear ? '^\d{4}(-(0[1-9]|1[0-2]))?$' : '^\d{4}-(0[1-9]|1[0-2])$')
            ->description($allowYear
                ? 'Miesiąc RRRR-MM (np. 2026-09) albo cały rok RRRR (np. 2026). Bez podania: bieżący miesiąc.'
                : 'Miesiąc RRRR-MM (np. 2026-09). Bez podania: bieżący miesiąc.');
    }

    private function period(Request $request, bool $allowYear = true): DateRange
    {
        $data = $request->validate([
            'period' => ['nullable', 'string', $allowYear ? 'regex:/^\d{4}(-(0[1-9]|1[0-2]))?$/D' : 'regex:/^\d{4}-(0[1-9]|1[0-2])$/D'],
        ], [
            'period.regex' => $allowYear
                ? 'Okres ma mieć postać RRRR-MM albo RRRR, na przykład 2026-09.'
                : 'Miesiąc ma mieć postać RRRR-MM, na przykład 2026-09.',
        ]);

        return isset($data['period']) ? DateRange::fromPrefix($data['period']) : DateRange::currentMonth();
    }
}
