<?php

use App\Domain\Audit\Models\ActivityEntry;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

function invitationToken(User $user): string
{
    return Password::broker('invitations')->createToken($user);
}

test('an invitation screen greets the trainer and shows what it is for', function () {
    $invited = User::factory()->invited()->create([
        'name' => 'Katarzyna Samborska',
        'email' => 'kasia@samtrening.com',
        'specialty' => 'Zdrowa ciąża',
    ]);

    $this->get(route('activation.create', ['token' => invitationToken($invited)]).'?email='.urlencode($invited->email))
        ->assertOk()
        ->assertSee('Witaj w studio, Katarzyna.')
        ->assertSee('Zaproszenie do zespołu')
        ->assertSee('Ustaw hasło.')
        ->assertSee('Link ważny 7 dni')
        ->assertSee('Zdrowa ciąża')
        ->assertSee('Zobowiązuję się do ochrony danych klientów')
        ->assertSee('Aktywuj konto →');
});

test('setting a password from an invitation activates the account and logs the trainer in', function () {
    $invited = User::factory()->invited()->create(['name' => 'Katarzyna Samborska']);

    $this->post(route('activation.store'), [
        'token' => invitationToken($invited),
        'email' => $invited->email,
        'password' => 'pierwsze-haslo',
        'password_confirmation' => 'pierwsze-haslo',
        'consent' => '1',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($invited->fresh());

    expect($invited->fresh()->status)->toBe(UserStatus::Active)
        ->and(Hash::check('pierwsze-haslo', $invited->fresh()->password))->toBeTrue()
        ->and(ActivityEntry::query()->orderByDesc('id')->first())
        ->action->toBe('Aktywował konto')
        ->actor_name->toBe('Katarzyna Samborska');
});

test('an invitation without the consent box is refused, and the account stays invited', function () {
    $invited = User::factory()->invited()->create();

    $this->post(route('activation.store'), [
        'token' => invitationToken($invited),
        'email' => $invited->email,
        'password' => 'pierwsze-haslo',
        'password_confirmation' => 'pierwsze-haslo',
    ])->assertSessionHasErrors(['consent' => 'Zaznacz zobowiązanie do ochrony danych klientów.']);

    expect($invited->fresh()->status)->toBe(UserStatus::Invited);
    $this->assertGuest();
});

test('a short password and a mismatch say exactly what is wrong', function () {
    $invited = User::factory()->invited()->create();
    $token = invitationToken($invited);

    $this->post(route('activation.store'), [
        'token' => $token,
        'email' => $invited->email,
        'password' => 'krótkie',
        'password_confirmation' => 'krótkie',
        'consent' => '1',
    ])->assertSessionHasErrors(['password' => 'Hasło musi mieć minimum 8 znaków.']);

    $this->post(route('activation.store'), [
        'token' => $token,
        'email' => $invited->email,
        'password' => 'dobre-haslo-123',
        'password_confirmation' => 'inne-haslo-123',
        'consent' => '1',
    ])->assertSessionHasErrors(['password' => 'Hasła się nie zgadzają.']);
});

test('an invitation link works for a week and not a day longer', function () {
    $invited = User::factory()->invited()->create();
    $token = invitationToken($invited);

    $this->travel(8)->days();

    $this->post(route('activation.store'), [
        'token' => $token,
        'email' => $invited->email,
        'password' => 'pierwsze-haslo',
        'password_confirmation' => 'pierwsze-haslo',
        'consent' => '1',
    ])->assertSessionHasErrors(['email' => 'Ten link jest nieważny albo został już użyty. Poproś o nowy.']);

    expect($invited->fresh()->status)->toBe(UserStatus::Invited);
});

test('an invitation link is spent once it is used', function () {
    $invited = User::factory()->invited()->create();
    $token = invitationToken($invited);

    $form = [
        'token' => $token,
        'email' => $invited->email,
        'password' => 'pierwsze-haslo',
        'password_confirmation' => 'pierwsze-haslo',
        'consent' => '1',
    ];

    $this->post(route('activation.store'), $form)->assertRedirect(route('dashboard'));

    auth()->logout();

    $this->post(route('activation.store'), $form)
        ->assertSessionHasErrors(['email' => 'Ten link jest nieważny albo został już użyty. Poproś o nowy.']);
});

test('the reset screen is the same screen, without the consent box', function () {
    $trainer = User::factory()->create(['name' => 'Bartek Nowak']);
    $token = Password::broker()->createToken($trainer);

    $this->get(route('password.reset', ['token' => $token]).'?email='.urlencode($trainer->email))
        ->assertOk()
        ->assertSee('Wracamy do gry, Bartek.')
        ->assertSee('Link ważny 60 minut · jednorazowy')
        ->assertSee('Zapisz nowe hasło →')
        ->assertDontSee('Zobowiązuję się do ochrony danych klientów');
});

test('a reset logs the trainer in and says the other devices are gone', function () {
    $trainer = User::factory()->create();

    $this->post(route('password.store'), [
        'token' => Password::broker()->createToken($trainer),
        'email' => $trainer->email,
        'password' => 'nowe-mocne-haslo',
        'password_confirmation' => 'nowe-mocne-haslo',
    ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('toast', 'Hasło zmienione. Pozostałe urządzenia zostały wylogowane.');

    $this->assertAuthenticatedAs($trainer->fresh());

    expect(ActivityEntry::query()->orderByDesc('id')->first()->action)->toBe('Ustawił nowe hasło');
});

test('a session opened before the password changed stops working', function () {
    $trainer = User::factory()->create();

    $this->actingAs($trainer)->get(route('dashboard'))->assertOk();

    // Another device changes the password — the hash carried in this session no longer matches.
    $trainer->forceFill(['password' => Hash::make('zupelnie-inne-haslo')])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
