<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\FindClients;
use App\Mcp\Tools\GetClient;
use App\Mcp\Tools\GetEarnings;
use App\Mcp\Tools\GetMonthSummary;
use App\Mcp\Tools\GetSessionReport;
use App\Mcp\Tools\ListOutstanding;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The studio CRM as a Claude connector — SC-68, docs/CLAUDE-CONNECTOR.md.
 *
 * Stage 1 reads and nothing else. Every tool calls a query the panel already uses, so an answer in
 * the conversation and the number on the screen come from the same code.
 */
#[Name('SAMTRENING CRM')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    CRM studia treningu personalnego SAMTRENING (Kraków). Rozmawiasz z właścicielem studia; odpowiadaj po polsku.

    - Kwoty są w złotych jako tekst ("1 250 zł") i w groszach w polach `*_minor` (12000 = 120 zł). Licz na groszach.
    - Klienta znajdziesz przez find_clients (po imieniu, nazwisku albo aliasie z kalendarza), a jego szczegóły przez get_client.
    - W find_clients `balance` ujemne oznacza zaległość, dodatnie — wpłatę z góry, jeszcze niewykorzystaną. W get_client i list_outstanding `owed` dodatnie oznacza, ile klient jest winien.
    - Sesje w CRM to treningi, które już się odbyły (albo zostały odwołane). Przyszłego grafiku tu nie ma — jest w Google Calendar.
    - Gdy dopasowujesz wydarzenia z kalendarza do klientów, porównuj z nazwiskiem i aliasami z find_clients; przy wątpliwości zapytaj, zamiast zgadywać.
    - Linki do raportów podawaj w całości — działają 15 minut.
    MARKDOWN)]
class StudioServer extends Server
{
    protected array $tools = [
        FindClients::class,
        GetClient::class,
        ListOutstanding::class,
        GetMonthSummary::class,
        GetEarnings::class,
        GetSessionReport::class,
    ];
}
