<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReadsPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The accountant's CSV, handed over as a link rather than as a file: Claude has no way to give
 * the owner a file on a phone, but a browser does. The link carries only the period and who
 * asked, the file is built when it is opened, and nothing is left lying on the disk.
 */
#[Name('get_session_report')]
#[Title('Raport CSV dla księgowej')]
#[Description('Link do pobrania raportu CSV sesji całego studia (data, klient, trener, usługa, rodzaj, kwota, status płatności) za miesiąc albo rok — ten sam plik co przycisk w panelu. Link działa 15 minut; podaj go użytkownikowi w całości, bez skracania.')]
#[IsReadOnly]
class GetSessionReport extends Tool
{
    use ReadsPeriod;

    /** Long enough to tap it on a phone, short enough that a link pasted somewhere goes dead. */
    public const int VALID_MINUTES = 15;

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $range = $this->period($request);
        $expires = now()->addMinutes(self::VALID_MINUTES);

        return Response::structured([
            'period' => $range->prefix(),
            'label' => $range->label(),
            'url' => URL::temporarySignedRoute('reports.sessions', $expires, [
                'period' => $range->prefix(),
                'by' => $request->user()->getAuthIdentifier(),
            ]),
            'expires_at' => $expires->setTimezone(config('app.timezone'))->toIso8601String(),
        ]);
    }
}
