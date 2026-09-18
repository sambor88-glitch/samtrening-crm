<?php

use App\Domain\Agent\CalendarAliases;
use App\Domain\Clients\Models\Client;

/*
 * docs/AGENT-API.md §4 — the field the dashboard matches calendar events on.
 * The cases below are the real entries from Maciek's calendar, named in the spec.
 */

beforeEach(function () {
    $this->aliases = new CalendarAliases;
});

function aliasesFor(string $name, array $others = []): array
{
    $client = Client::factory()->create(['name' => $name]);

    foreach ($others as $other) {
        Client::factory()->create(['name' => $other]);
    }

    return (new CalendarAliases)->for($client, Client::query()->get());
}

test('the surname is always there — it is the minimum a card can match on', function () {
    expect(aliasesFor('Jakub Żurek'))->toContain('żurek');
    expect(aliasesFor('Tomasz Róg'))->toContain('róg');
    expect(aliasesFor('Katarzyna Bogucka'))->toContain('bogucka');
});

test('a name typed without Polish letters still matches', function () {
    expect(aliasesFor('Jakub Żurek'))->toContain('zurek');
    expect(aliasesFor('Tomasz Róg'))->toContain('rog');
    expect(aliasesFor('Małgorzata Lidacka'))->toContain('malgorzata');
});

test('a bare first name works, because half the calendar says only that', function () {
    // "Anna trening", "Trening Anna", "Anna - trening" all reduce to this one word.
    expect(aliasesFor('Anna Motkowicz'))->toContain('anna');
});

test('the usual Polish short form is there too', function () {
    expect(aliasesFor('Katarzyna Bogucka'))->toContain('kasia');
    expect(aliasesFor('Wojciech Solecki'))->toContain('wojtek');
    expect(aliasesFor('Jakub Żurek'))->toContain('kuba');
});

test('a short form pairs up with the surname as well', function () {
    // "Kasia Bogucka" is how that client is written down, and it is two words.
    expect(aliasesFor('Katarzyna Bogucka'))->toContain('kasia bogucka');
});

test('a first name two clients share is never generated', function () {
    $aliases = aliasesFor('Anna Motkowicz', others: ['Anna Kowalska']);

    // Charging "Anna trening" to whichever Anna came first is worse than not matching it.
    expect($aliases)->not->toContain('anna')
        ->and($aliases)->not->toContain('ania')
        ->and($aliases)->toContain('motkowicz')
        ->and($aliases)->toContain('anna motkowicz');
});

test('a short form that collides with somebody else is refused as well', function () {
    $aliases = aliasesFor('Katarzyna Bogucka', others: ['Kasia Nowak']);

    expect($aliases)->not->toContain('kasia')
        ->and($aliases)->toContain('bogucka');
});

test('two clients sharing a surname keep it — losing it would leave nothing', function () {
    $aliases = aliasesFor('Tomasz Róg', others: ['Magdalena Róg']);

    expect($aliases)->toContain('róg')
        ->and($aliases)->toContain('tomasz')
        ->and($aliases)->toContain('tomasz róg');
});

test('aliases are lower case, unpunctuated and free of duplicates', function () {
    $aliases = aliasesFor('Anna  MOTKOWICZ-Nowak');

    expect($aliases)->toBe(array_values(array_unique($aliases)));

    foreach ($aliases as $alias) {
        expect($alias)->toBe(mb_strtolower($alias))
            ->and($alias)->not->toMatch('/[-.,;:()]/')
            ->and($alias)->not->toMatch('/\s{2,}/')
            ->and(trim($alias))->toBe($alias);
    }
});

test('a pair training together has to be written down by hand', function () {
    // "Ula I Gosia" is one session for Małgorzata Lidacka, and no rule gets there from her name.
    $client = Client::factory()->create([
        'name' => 'Małgorzata Lidacka',
        'calendar_aliases' => ['ula gosia', 'lidacka'],
    ]);

    expect($this->aliases->for($client, Client::query()->get()))->toBe(['ula gosia', 'lidacka']);
});

test('an empty list on the card is a decision, not a gap to fill', function () {
    $client = Client::factory()->create(['name' => 'Anna Motkowicz', 'calendar_aliases' => []]);

    expect($this->aliases->for($client, Client::query()->get()))->toBe([]);
});

test('a one-word name still yields something to match on', function () {
    expect(aliasesFor('Madonna'))->toBe(['madonna']);
});
