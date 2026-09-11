<?php

test('a button is a secondary <button type="button"> by default', function () {
    $this->blade('<x-btn>Anuluj</x-btn>')
        ->assertSee('type="button"', false)
        ->assertSee('class="btn btn-secondary"', false)
        ->assertSee('Anuluj');
});

test('a button with an href renders as a link', function () {
    $this->blade('<x-btn variant="primary" href="/klienci" block>Karta</x-btn>')
        ->assertSee('<a href="/klienci"', false)
        ->assertSee('btn btn-primary btn-block', false);
});

test('a tag carries its variant', function () {
    $this->blade('<x-tag variant="outline">Poproszono</x-tag>')
        ->assertSee('class="tag tag-outline"', false);
});

test('an input links its label and shows the hint and the validation message', function () {
    $this->withViewErrors(['email' => 'Podaj adres e-mail.'])
        ->blade('<x-input name="email" type="email" label="E-mail" hint="Konto zakłada właściciel studia." />')
        ->assertSee('<label for="email">E-mail</label>', false)
        ->assertSee('id="email"', false)
        ->assertSee('type="email"', false)
        ->assertSeeInOrder(['Konto zakłada właściciel studia.', 'Podaj adres e-mail.']);
});

test('a segmented control checks the current option', function () {
    $view = $this->blade('<x-seg name="filter" :options="$options" value="archive" />', [
        'options' => ['active' => 'Aktywni', 'archive' => 'Archiwum'],
    ]);

    $view->assertSeeInOrder(['Aktywni', 'Archiwum']);
    expect((string) $view)->toMatch('/value="archive"\s+checked/')
        ->not->toMatch('/value="active"\s+checked/');
});

test('the stat bar lists label, value and hint for each cell', function () {
    $this->blade('<x-stat-bar :items="$items" />', ['items' => [
        ['label' => 'Nierozliczone', 'value' => '1 250 zł', 'hint' => '3 klientów z saldem'],
        ['label' => 'Aktywni klienci', 'value' => '12'],
    ]])->assertSeeInOrder(['Nierozliczone', '1 250 zł', '3 klientów z saldem', 'Aktywni klienci', '12']);
});

test('the data table prints its column headers above the rows', function () {
    $this->blade(
        '<x-data-table :columns="$columns"><tr><td data-label="Klient">Anna</td></tr></x-data-table>',
        ['columns' => ['Klient', 'Stawka']],
    )->assertSeeInOrder(['<th scope="col">Klient</th>', '<th scope="col">Stawka</th>', 'data-label="Klient"'], false);
});

test('the empty state shows its action', function () {
    $this->blade('<x-empty-state title="Brak klientów">Kartoteka jest pusta.<x-slot:action><x-btn variant="primary">＋ Dodaj klienta</x-btn></x-slot:action></x-empty-state>')
        ->assertSeeInOrder(['Brak klientów', 'Kartoteka jest pusta.', '＋ Dodaj klienta']);
});

test('skeleton rows render the requested number of rows', function () {
    $html = (string) $this->blade('<x-skeleton-rows :rows="5" :height="48" />');

    expect(substr_count($html, 'class="skeleton-row"'))->toBe(5)
        ->and($html)->toContain('height: 48px');
});

test('the toast listens for toast events and announces them politely', function () {
    $this->blade('<x-toast />')
        ->assertSee('x-on:toast.window', false)
        ->assertSee('aria-live="polite"', false);
});

test('the styleguide is not served outside local development', function () {
    $this->get('/_styleguide')->assertNotFound();
});
