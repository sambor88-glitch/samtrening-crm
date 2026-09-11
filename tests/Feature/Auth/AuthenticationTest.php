<?php

use App\Domain\Team\Models\User;

test('the login screen carries the studio copy, not Breeze', function () {
    User::factory()->owner()->create(['name' => 'Maciej Samborski', 'email' => 'maciek@samtrening.com']);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Zaloguj się.')
        ->assertSee('Konto zakłada właściciel studia.')
        ->assertSee('Nie pamiętam hasła')
        ->assertSee('Problem z dostępem albo konto zablokowane?')
        ->assertSee('Maciej · maciek@samtrening.com')
        ->assertDontSee('Remember me')
        ->assertDontSee('PROTOTYP');
});

test('an empty form names each missing field', function () {
    $this->post(route('login'), [])->assertSessionHasErrors([
        'email' => 'Podaj adres e-mail.',
        'password' => 'Podaj hasło.',
    ]);

    $this->assertGuest();
});

test('an unknown address is told that the owner has to create the account', function () {
    $this->post(route('login'), ['email' => 'nikt@samtrening.com', 'password' => 'password'])
        ->assertSessionHasErrors([
            'email' => 'Nie znamy tego adresu. Reset hasła tu nie pomoże — konto musi założyć właściciel studia.',
        ]);

    $this->assertGuest();
});

test('a blocked account is refused even with the right password', function () {
    $blocked = User::factory()->blocked()->create();

    $this->post(route('login'), ['email' => $blocked->email, 'password' => 'password'])
        ->assertSessionHasErrors([
            'email' => 'Konto zablokowane. Reset hasła tego nie zmieni — odblokować może tylko właściciel studia.',
        ]);

    $this->assertGuest();
});

test('an invited account is sent to set a password instead of being told off', function () {
    $invited = User::factory()->invited()->create();

    $this->post(route('login'), ['email' => $invited->email, 'password' => 'cokolwiek'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors();

    $this->assertGuest();

    $this->get(route('password.request'))
        ->assertSee('Ustaw hasło.')
        ->assertSee('Konto już na Ciebie czeka.');
});

test('a wrong password is named as such', function () {
    $trainer = User::factory()->create();

    $this->post(route('login'), ['email' => $trainer->email, 'password' => 'nie-to-haslo'])
        ->assertSessionHasErrors(['email' => 'Hasło nie pasuje do tego adresu.']);

    $this->assertGuest();
});

test('the address is locked out after five refused attempts', function () {
    $trainer = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $trainer->email, 'password' => 'nie-to-haslo']);
    }

    $this->post(route('login'), ['email' => $trainer->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toStartWith('Za dużo prób logowania.');

    $this->assertGuest();
});

test('the right password lands the trainer on the dashboard', function () {
    $trainer = User::factory()->create();

    $this->post(route('login'), ['email' => $trainer->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($trainer);
});

test('a logged in trainer who opens the login screen goes to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('trainers can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});
