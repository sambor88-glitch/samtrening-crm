<?php

use App\Domain\Settings\Models\Setting;
use App\Domain\Team\Models\User;

test('guests are sent to the login screen', function () {
    $this->get('/pulpit')->assertRedirect(route('login'));
});

test('a trainer sees the trainer panel and no role switch at all', function () {
    $trainer = User::factory()->create(['name' => 'Katarzyna Samborska']);

    $this->actingAs($trainer)->get('/pulpit')
        ->assertOk()
        ->assertSee('CRM · PANEL TRENERA')
        ->assertSeeInOrder(['Pulpit', 'Klienci', 'Sesje', 'Płatności', 'Zarobki', 'Wiadomości', 'Ustawienia'])
        ->assertSee('Katarzyna Samborska')
        ->assertSee('＋ Wbij sesję')
        ->assertDontSee('Widok')
        ->assertDontSee(route('admin.dashboard').'"', false);
});

test('a trainer cannot open the admin panel', function () {
    $trainer = User::factory()->create();

    $this->actingAs($trainer)->get('/admin')->assertForbidden();
    $this->actingAs($trainer)->get('/admin/trenerzy')->assertForbidden();
});

test('the owner switches between the trainer and the admin panel', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/pulpit')
        ->assertSee('Widok')
        ->assertSee(route('admin.dashboard').'"', false);

    $this->actingAs($owner)->get('/admin')
        ->assertOk()
        ->assertSee('CRM · PANEL ADMINA')
        ->assertSeeInOrder(['Pulpit', 'Trenerzy', 'Klienci studia', 'Zaległości', 'Log zmian'])
        ->assertSee('＋ Zaproś trenera');
});

test('the current screen is marked in the navigation', function () {
    $html = $this->actingAs(User::factory()->create())->get('/klienci')->getContent();

    expect($html)->toMatch('#href="'.preg_quote(route('clients.index'), '#').'"\s+aria-current="page"#')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});

test('the ticker runs by default and can be switched off in the settings', function () {
    $trainer = User::factory()->create();

    $this->actingAs($trainer)->get('/pulpit')->assertSee('Płacisz za odbyte sesje');

    Setting::query()->create(['ticker_enabled' => false]);

    $this->actingAs($trainer)->get('/pulpit')->assertDontSee('Płacisz za odbyte sesje');
});
